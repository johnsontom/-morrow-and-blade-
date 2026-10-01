<?php
/**
 * The site assistant.
 *
 * Two engines, same knowledge:
 *
 *   1. If an API key is present (config/local.php or OPENAI_API_KEY), the
 *      question and the live database brief go to the model, which writes
 *      the answer in natural language.
 *   2. If there is no key, or the call fails, a rule-based answering engine
 *      reads the same brief and replies with facts from the database.
 *
 * Either way the answer is grounded: prices, hours and availability come
 * from MySQL, never from the model's imagination.
 */

declare(strict_types=1);

final class Assistant
{
    public static function configured(): bool
    {
        return defined('AI_API_KEY') && AI_API_KEY !== '';
    }

    /**
     * @param array<int, array{role:string, content:string}> $history
     * @return array{answer:string, engine:string}
     */
    public static function reply(string $question, array $history = [], ?array $branch = null): array
    {
        $question = trim($question);

        if ($question === '') {
            return ['answer' => 'Ask me about prices, availability, our team or where to find us.', 'engine' => 'rules'];
        }

        if (self::configured()) {
            $answer = self::askModel($question, $history, $branch);

            if ($answer !== null) {
                return ['answer' => $answer, 'engine' => 'openai'];
            }
        }

        return ['answer' => self::rules($question, $branch), 'engine' => 'rules'];
    }

    // ------------------------------------------------------------- the model

    private static function askModel(string $question, array $history, ?array $branch = null): ?string
    {
        $messages = [[
            'role' => 'system',
            'content' => "You are the front desk assistant for " . SITE_NAME . ", a London barber and beauty salon.\n"
                . "Answer only from the FACTS below. If something is not in the facts, say you are not sure and offer the\n"
                . "salon phone number. Be warm and brief - two or three sentences. Use the real prices and times.\n\n"
                . "FACTS\n" . Grounding::brief($branch),
        ]];

        foreach (array_slice($history, -8) as $turn) {
            if (!isset($turn['role'], $turn['content'])) {
                continue;
            }

            $role = $turn['role'] === 'assistant' ? 'assistant' : 'user';
            $messages[] = ['role' => $role, 'content' => (string) $turn['content']];
        }

        $messages[] = ['role' => 'user', 'content' => $question];

        $payload = json_encode([
            'model' => defined('AI_MODEL') ? AI_MODEL : 'gpt-4o-mini',
            'messages' => $messages,
            'temperature' => 0.3,
            'max_tokens' => 400,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $endpoint = defined('AI_ENDPOINT') ? AI_ENDPOINT : 'https://api.openai.com/v1/chat/completions';

        $curl = curl_init($endpoint);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . AI_API_KEY,
            ],
            CURLOPT_TIMEOUT => 25,
            CURLOPT_CONNECTTIMEOUT => 8,
        ]);

        $response = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if (!is_string($response) || $status < 200 || $status >= 300) {
            return null;
        }

        $decoded = json_decode($response, true);
        $answer = $decoded['choices'][0]['message']['content'] ?? null;

        return is_string($answer) && trim($answer) !== '' ? trim($answer) : null;
    }

    // ---------------------------------------------------------- rule engine

    private static function rules(string $question, ?array $branch = null): string
    {
        $q = mb_strtolower($question);
        $has = static function (array $needles) use ($q): bool {
            foreach ($needles as $needle) {
                if (mb_strpos($q, $needle) !== false) {
                    return true;
                }
            }

            return false;
        };

        // --- money -------------------------------------------------------
        if ($has(['price', 'cost', 'how much', 'charge', 'fee', 'cheap', 'rate'])) {
            $matches = self::matchServices($q, $branch);

            if ($matches !== []) {
                $parts = [];
                foreach ($matches as $service) {
                    $parts[] = sprintf('%s is %s (%s)', $service['name'], money((int) $service['price_pence']), duration((int) $service['duration_minutes']));
                }

                return implode('. ', $parts) . '. Prices are the same at all three branches.';
            }

            $lines = ['Our treatments start at ' . money(self::cheapestPrice($branch)) . '.'];
            foreach (array_slice(self::catalogue($branch), 0, 6) as $service) {
                $lines[] = $service['name'] . ' - ' . money((int) $service['price_pence']);
            }

            return implode(' ', $lines) . ' Tell me which treatment you had in mind and I will give you the exact figure.';
        }

        // --- availability -------------------------------------------------
        if ($has(['available', 'free', 'who can', 'open now', 'right now', 'busy', 'today'])) {
            $free = [];
            $busy = [];

            foreach (BarberDAO::withAvailability(null, $branch['id'] ?? null) as $barber) {
                if (($barber['status'] ?? '') === 'available') {
                    $free[] = $barber['name'];
                } else {
                    $busy[] = $barber['name'] . ' (' . mb_strtolower((string) $barber['status_message']) . ')';
                }
            }

            $answer = $free !== []
                ? 'Free right now: ' . implode(', ', $free) . '.'
                : 'Nobody is free this minute.';

            if ($busy !== []) {
                $answer .= ' Otherwise: ' . implode(', ', $busy) . '.';
            }

            return $answer . ' You can take any of those slots on the booking page.';
        }

        // --- location -----------------------------------------------------
        if ($has(['where', 'address', 'location', 'nearest', 'closest', 'directions', 'parking', 'branch', 'find you'])) {
            $branches = BranchDAO::all();
            $parts = [];

            foreach ($branches as $branch) {
                $parts[] = sprintf('%s at %s, %s %s', $branch['name'], $branch['address_line_1'], $branch['city'], $branch['postcode']);
            }

            return 'We have ' . count($branches) . ' London branches: ' . implode('; ', $parts)
                . '. The Visit us page has a map and a one-tap directions link for each one.';
        }

        // --- hours --------------------------------------------------------
        if ($has(['hour', 'open', 'close', 'closing', 'opening time', 'sunday', 'saturday', 'bank holiday', 'late'])) {
            $salon = SalonDAO::primary();
            $parts = [];

            foreach (($salon['opening_hours_decoded'] ?? []) as $day) {
                $parts[] = ($day['day'] ?? '') . ' ' . ($day['hours'] ?? '');
            }

            return 'Our hours are: ' . implode(', ', $parts) . '. If you arrive close to closing time, it is worth ringing first.';
        }

        // --- team ---------------------------------------------------------
        if ($has(['barber', 'team', 'staff', 'who', 'stylist', 'therapist', 'specialist', 'best person', 'recommend'])) {
            $parts = [];

            foreach (BarberDAO::withAvailability(null, $branch['id'] ?? null) as $barber) {
                $parts[] = sprintf('%s (%s, %d years%s)', $barber['name'], $barber['role'], (int) $barber['years_experience'], $barber['specialties_list'] !== [] ? ', ' . implode(' and ', array_slice($barber['specialties_list'], 0, 3)) : '');
            }

            return 'The team: ' . implode('; ', $parts) . '. You can meet them properly on the Team page and pick one when you book.';
        }

        // --- services -----------------------------------------------------
        if ($has(['service', 'treatment', 'do you do', 'offer', 'massage', 'manicure', 'pedicure', 'nail', 'beard', 'fade', 'colour', 'color', 'style'])) {
            $matches = self::matchServices($q, $branch);

            if ($matches !== []) {
                $detail = [];
                foreach ($matches as $service) {
                    $detail[] = sprintf('%s (%s, %s)', $service['name'], money((int) $service['price_pence']), duration((int) $service['duration_minutes']));
                }

                return 'Yes - ' . implode(', ', $detail) . '. Anything else takes your fancy?';
            }

            $parts = [];
            foreach (ServiceDAO::categories() as $category) {
                $parts[] = $category['name'];
            }

            return 'We cover ' . implode(', ', $parts) . '. Ask me about any of them and I will give you the price and how long it takes.';
        }

        // --- booking ------------------------------------------------------
        if ($has(['book', 'appointment', 'reserve', 'slot', 'cancel', 'reschedule'])) {
            return 'Booking takes about a minute: choose the treatment, pick the team member, then the time. You get a reference straight away'
                . ' and the slot is held in the system. Guests can book without an account, and cancelling is free up to 24 hours before.';
        }

        // --- fallback -----------------------------------------------------
        return 'I can help with prices, what is available today, our team, opening hours and how to find us. '
            . 'For anything medical or for a complaint, the quickest route is ' . self::phone() . '.';
    }

    /** Services whose name, category or description mentions a word in the question. */
    private static function matchServices(string $q, ?array $branch = null): array
    {
        $stop = ['how', 'much', 'is', 'the', 'a', 'an', 'for', 'of', 'do', 'you', 'what', 'price', 'cost', 'charge', 'and', 'to', 'in', 'at', 'my', 'i'];
        $words = array_values(array_filter(
            preg_split('/[^a-z0-9]+/', $q) ?: [],
            static fn ($word) => mb_strlen($word) > 2 && !in_array($word, $stop, true)
        ));

        // A hit in the service name counts for more than a word buried in the
        // description, so "skin fade" beats a pedicure that mentions skin.
        $scored = [];

        foreach (self::catalogue($branch) as $service) {
            $name = mb_strtolower($service['name']);
            $category = mb_strtolower((string) ($service['category_name'] ?? ''));
            $description = mb_strtolower((string) ($service['description'] ?? ''));
            $score = 0;

            foreach ($words as $word) {
                $stem = rtrim($word, 's');

                if (mb_strpos($name, $stem) !== false) {
                    $score += 3;
                } elseif (mb_strpos($category, $stem) !== false) {
                    $score += 2;
                } elseif (mb_strpos($description, $stem) !== false) {
                    $score += 1;
                }
            }

            if ($score > 0) {
                $scored[] = ['score' => $score, 'service' => $service];
            }
        }

        if ($scored === []) {
            return [];
        }

        usort($scored, static fn ($a, $b) => $b['score'] <=> $a['score']);
        $best = $scored[0]['score'];
        $floor = max(1, (int) ceil($best / 2));

        $matches = [];
        foreach ($scored as $entry) {
            if ($entry['score'] < $floor) {
                continue;
            }

            $matches[] = $entry['service'];
        }

        return array_slice($matches, 0, 3);
    }

    private static function catalogue(?array $branch = null): array
    {
        static $cache = [];
        $key = $branch['id'] ?? 'all';

        if (!isset($cache[$key])) {
            $cache[$key] = $branch !== null ? ServiceDAO::allAtSalon((string) $branch['id']) : ServiceDAO::all();
        }

        return $cache[$key];
    }

    private static function cheapestPrice(?array $branch = null): int
    {
        $prices = array_map(static fn ($service) => (int) $service['price_pence'], self::catalogue($branch));

        return $prices === [] ? 0 : min($prices);
    }

    private static function phone(): string
    {
        $salon = SalonDAO::primary();

        return $salon !== null ? $salon['phone'] : 'the salon';
    }
}
