# --- Build ---
FROM node:22-alpine AS build

WORKDIR /app

COPY frontend/package*.json ./
RUN npm ci --prefer-offline || npm install

COPY frontend/ .

ARG VITE_API_BASE_URL
ENV VITE_API_BASE_URL=$VITE_API_BASE_URL

RUN npm run build

# --- Serve ---
FROM nginx:alpine

COPY --from=build /app/dist /usr/share/nginx/html
COPY frontend/docker/nginx-spa.conf /etc/nginx/conf.d/default.conf

EXPOSE 80
