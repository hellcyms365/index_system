# 上网入口 - Docker 镜像（可选部署方式）
# 使用方式：docker compose up -d --build
# 基础镜像使用国内镜像源前缀（2026-10 实测可用）。
# 如需改回 Docker Hub 官方源或你自己的加速器，改回：
# FROM php:8.2-apache
FROM docker.1panel.live/library/php:8.2-apache

# 安装 PHP MySQL 扩展（官方镜像自带 docker-php-ext-install）
RUN docker-php-ext-install pdo_mysql

# 将 Apache 站点根目录指向 public/
# 注意：sed 表达式必须用双引号，${APACHE_DOCUMENT_ROOT} 才能在构建时展开；
# 分隔符用 ! 避免与路径中的 / 冲突。
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e "s!/var/www/html!${APACHE_DOCUMENT_ROOT}!g" /etc/apache2/sites-available/*.conf \
 && sed -ri -e "s!/var/www/!${APACHE_DOCUMENT_ROOT}!g" /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

COPY app /var/www/html/app
COPY public /var/www/html/public
COPY database.sql /var/www/html/database.sql

WORKDIR /var/www/html
