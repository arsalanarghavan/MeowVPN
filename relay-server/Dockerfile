FROM node:22-bookworm-slim

WORKDIR /app

COPY package.json package-lock.json* ./
RUN npm install --omit=dev --no-audit --no-fund

COPY dist ./dist

ENV NODE_ENV=production
ENV RELAY_DATA_DIR=/data

EXPOSE 3100

CMD ["node", "dist/index.js"]
