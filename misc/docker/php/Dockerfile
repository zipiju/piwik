FROM ubuntu:14.04

ENV DEBIAN_FRONTEND noninteractive

RUN apt-get install -y software-properties-common
RUN apt-add-repository multiverse

RUN apt-get update -qq && apt-get install -y --quiet git php5-cli php5-curl php5-mcrypt php5-mysql php5-gd phantomjs imagemagick imagemagick-doc mysql-client && apt-get clean && rm -rf /var/lib/apt/lists/*

# Fonts needed for the UI tests
COPY fonts/* /usr/share/fonts/

# PHP config
RUN echo "date.timezone = UTC" > /etc/php5/cli/conf.d/custom.ini
RUN echo "always_populate_raw_post_data = -1" >> /etc/php5/cli/conf.d/custom.ini

# This locale is used in tests
RUN locale-gen de_DE

WORKDIR /app
