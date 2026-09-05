# ============================================================
# ADC Portal — React RIS Frontend & Application Server
# Multi-stage production build: Node.js 20 build -> Nginx Alpine
# ============================================================
FROM node:20-alpine AS build
WORKDIR /app

COPY package.json ./
RUN npm install --legacy-peer-deps

COPY . .
RUN npm run build

FROM nginx:alpine AS runtime
COPY --from=build /app/dist /usr/share/nginx/html

RUN printf 'server {\n\
    listen 80;\n\
    server_name _;\n\
    root /usr/share/nginx/html;\n\
    index index.html;\n\
\n\
    location / {\n\
        try_files $uri $uri/ /index.html;\n\
    }\n\
\n\
    location ~* \\.(?:css|js|jpg|jpeg|gif|png|ico|svg|woff|woff2|ttf|eot)$ {\n\
        expires 30d;\n\
        add_header Cache-Control "public, no-transform";\n\
    }\n\
}\n' > /etc/nginx/conf.d/default.conf

# Preserve compatibility directories for existing VPS bind-mounts
RUN mkdir -p /var/www/html/storage /var/www/html/public/uploads

EXPOSE 80
CMD ["nginx", "-g", "daemon off;"]
