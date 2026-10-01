FROM node:22-alpine

ENV NODE_ENV=production

WORKDIR /app

# Dependencies first, so a source change does not bust the layer cache.
COPY package*.json ./
RUN npm install --omit=dev --no-audit --no-fund

COPY . .

EXPOSE 8087

# Drop privileges, then let the runtime replace itself with the node process so
# `docker compose stop` reaches it as SIGTERM and it shuts down cleanly.
USER node

CMD ["node", "src/index.js"]