FROM node:22-bookworm-slim

WORKDIR /app

COPY package.json package-lock.json* ./
RUN npm ci --omit=dev

COPY dist ./dist

ENV NODE_ENV=production
ENV RELAY_DATA_DIR=/data

EXPOSE 3100

CMD ["node", "dist/index.js"]
