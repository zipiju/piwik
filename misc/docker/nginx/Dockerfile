FROM phusion/baseimage

RUN apt-get update && apt-get install -y nginx && apt-get clean && rm -rf /var/lib/apt/lists/*

# forward request and error logs to docker log collector
RUN ln -sf /dev/stdout /var/log/nginx/access.log
RUN ln -sf /dev/stderr /var/log/nginx/error.log

EXPOSE 80

COPY default /etc/nginx/sites-enabled/default

CMD ["nginx", "-g", "daemon off;"]
