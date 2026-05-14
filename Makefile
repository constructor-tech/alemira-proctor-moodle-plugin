SHELL := /bin/bash
PYTHON ?= python3
DOCKER_IMAGE ?= proctor-moodle-plugin-release

# Prefer locally installed shifter (node_modules/.bin/shifter, populated by
# `npm install`) so `make release` works without Docker or a global install.
# Falls back to PATH-resolved `shifter` if no local one is found.
SHIFTER := $(shell test -x node_modules/.bin/shifter && echo node_modules/.bin/shifter || echo shifter)

.PHONY: yui-build yui-build-docker release release-docker

yui-build:
	cd yui/src && ../../$(SHIFTER) --recursive --no-lint || (cd yui/src && shifter --recursive --no-lint)

yui-build-docker:
	docker build -t $(DOCKER_IMAGE) .
	docker run --rm \
		-u "$(shell id -u)":"$(shell id -g)" \
		-e HOME=/tmp \
		-v "$(PWD)":/work \
		-w /work \
		$(DOCKER_IMAGE) \
		bash -lc 'cd yui/src && shifter --recursive --no-lint'

release: yui-build
	$(PYTHON) utils/release.py

release-docker:
	docker build -t $(DOCKER_IMAGE) .
	docker run --rm \
		-u "$(shell id -u)":"$(shell id -g)" \
		-e HOME=/tmp \
		-v "$(PWD)":/work \
		-w /work \
		$(DOCKER_IMAGE) \
		bash -lc 'cd yui/src && shifter --recursive --no-lint && cd /work && python3 utils/release.py'
