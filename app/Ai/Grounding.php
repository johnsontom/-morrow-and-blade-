<?php
/**
 * Turns the live database into a compact knowledge sheet for the assistant.
 *
 * The assistant never invents prices or availability: everything it is
 * allowed to talk about is assembled here straight from MySQL.
 */

declare(strict_types=1);

final class Grounding
{
    /**
     * A plain-text brief, small enough to send with every question.
     *
     * When the visitor is browsing one branch the menu and the team are
     * scoped to it, so the assistant cannot promise someone who works in a
     * different salon.
     */
    public static function brief(?array $branch = null): string
    {
        $lines = [];
        $salon = $branch ?? SalonDAO::primary();

        $lines[] = '== THE BUSINESS ==';
        $lines[] = SITE_NAME . ' - ' . SITE_TAGLINE;

        if ($branch !== null) {
            $lines[] = 'The visitor is browsing the ' . $branch['name'] . ' branch. Prefer answers for that salon.';
        }

        if ($salon !== null) {
            $lines[] = 'Address: ' . trim($salon['address_line_1'] . ', ' . $salon['city'] . ' ' . $salon['postcode'], ', ');
            $lines[] = 'Phone: ' . $salon['phone'];
            $lines[] = 'Email: ' . $salon['email'];

            $hours = [];
            foreach (($salon['opening_hours_decoded'] ?? []) as $day) {
                $hours[] = ($day['shortDay'] ?? '') . ' ' . ($day['hours'] ?? '');
            }
            if ($hours !== []) {
                $lines[] = 'Opening hours: ' . implode('; ', $hours);
            }
        }

        $lines[] = '';
        $lines[] = '== BRANCHES ==';
        foreach (BranchDAO::all() as $branch) {
            $lines[] = sprintf(
                '- %s, %s, %s %s (tel %s)%s',
                $branch['name'],
                $branch['address_line_1'],
                $branch['city'],
                $branch['postcode'],
                $branch['phone'],
                (int) $branch['is_primary'] === 1 ? ' [flagship]' : ''
            );
        }

        $lines[] = '';
        $lines[] = '== SERVICES AND PRICES (prices in GBP) ==';
        foreach (($branch !== null ? ServiceDAO::allAtSalon((string) $branch['id']) : ServiceDAO::all()) as $service) {
            $lines[] = sprintf(
                '- %s [%s]: %s, %s%s',
                $service['name'],
                $service['category_name'] ?? 'General',
                money((int) $service['price_pence']),
                duration((int) $service['duration_minutes']),
                !empty($service['description']) ? ' - ' . trim((string) $service['description']) : ''
            );
        }

        $lines[] = '';
        $lines[] = '== TEAM AND LIVE STATUS ==';
        foreach (BarberDAO::withAvailability(null, $branch['id'] ?? null) as $barber) {
            $lines[] = sprintf(
                '- %s (%s, %s, %d yrs, rating %s): %s. Specialities: %s. Today: %s - %s',
                $barber['name'],
                $barber['role'],
                $barber['staff_kind_label'] ?? 'Barber',
                (int) $barber['years_experience'],
                $barber['rating'],
                trim((string) $barber['bio']) !== '' ? trim((string) $barber['bio']) : 'No bio yet',
                implode(', ', $barber['specialties_list'] ?? []),
                $barber['working_hours_label'] ?? 'Closed',
                $barber['status_message'] ?? ''
            );
        }

        $lines[] = '';
        $lines[] = '== HOW BOOKING WORKS ==';
        $lines[] = 'Customers pick a service, a team member and a time on the booking page. Slots are 15 minutes apart';
        $lines[] = 'and a slot is only offered when the selected team member is genuinely free for the whole appointment,';
        $lines[] = 'so a double booking is refused. A booking reference is issued straight away. Cancellations are free';
        $lines[] = 'up to 24 hours before the appointment. Guests can book without an account, but creating an account lets';
        $lines[] = 'a customer see their history and message their barber directly.';

        return implode("\n", $lines);
    }

    /** A short list of things a first-time visitor usually asks. */
    public static function suggestions(): array
    {
        return [
            'How much is a skin fade?',
            'Who is free this afternoon?',
            'Where is your nearest salon?',
            'Do you do massages and nails?',
            'What time do you close on Saturday?',
        ];
    }
}
