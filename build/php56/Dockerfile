FROM php:5.6-fpm

# 更换 apt-get 源
RUN mv /etc/apt/sources.list /etc/apt/source.list.bak
COPY sources.list /etc/apt/sources.list
RUN apt-get -o Acquire::Check-Valid-Until=false update && apt-get -y upgrade

# 安装基础组件
RUN apt-get install -y libpng-dev libjpeg-dev libfreetype6-dev ntpdate cron vim unzip git libssl-dev

# 设置时区 && 同步时间
ENV TZ=Asia/Shanghai
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone \
	&& ntpdate 182.92.12.11

# 安装 Mongo 扩展
COPY mongo.tgz /home/mongo.tgz
WORKDIR /home
RUN tar -zxvf mongo.tgz
WORKDIR /home/mongo-php-driver-legacy-master
RUN phpize \
	&& ./configure \
	&& make && make install \
	&& echo "extension=mongo.so" > /usr/local/etc/php/conf.d/mongo.ini

# 安装扩展
RUN docker-php-ext-configure gd --with-freetype-dir --with-jpeg-dir
RUN docker-php-ext-install gd pdo_mysql mysqli
