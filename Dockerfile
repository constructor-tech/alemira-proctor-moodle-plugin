FROM node:20-bullseye

RUN apt-get update \
    && apt-get install -y python3 zip \
    && npm install -g shifter \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /work
