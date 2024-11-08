# 使用 PHP 官方鏡像，帶有 Apache 伺服器
FROM php:8.0-apache

# 將您的專案檔案複製到容器中的 Apache 網頁伺服器根目錄
COPY . /var/www/html/

# 開放容器的 80 端口以便外部訪問
EXPOSE 80
