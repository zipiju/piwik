FROM ubuntu:14.04

RUN apt-get update -qq && apt-get install -y --quiet git php5-cli php5-curl php5-mcrypt php5-mysql php5-gd php5-fpm && apt-get clean && rm -rf /var/lib/apt/lists/*

RUN mkdir -p /var/run/php5-fpm

# PHP config
RUN echo "date.timezone = UTC" > /etc/php5/cli/conf.d/custom.ini
RUN echo "always_populate_raw_post_data = -1" >> /etc/php5/cli/conf.d/custom.ini
# apply the same config for php-fpm
RUN ln -sf /etc/php5/cli/conf.d/custom.ini /etc/php5/fpm/conf.d/custom.ini

# This locale is used in tests
RUN locale-gen de_DE

# forward logs to docker log collector
RUN ln -sf /dev/stderr /var/log/php-fpm.log

COPY php-fpm.conf /etc/php5/fpm/php-fpm.conf

WORKDIR /app

CMD ["php5-fpm", "--allow-to-run-as-root"]
