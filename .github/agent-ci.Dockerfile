FROM ghcr.io/actions/actions-runner:latest

RUN sudo apt-get update \
	&& sudo apt-get install -y --no-install-recommends \
		composer \
		docker.io \
		php-cli \
		php-curl \
		php-mbstring \
		php-sqlite3 \
		php-xml \
		php-zip \
		xz-utils \
	&& sudo rm -rf /var/lib/apt/lists/*
