SHELL := /bin/bash
PYTHON ?= python3
DOCKER_IMAGE ?= proctor-moodle-plugin-release

.PHONY: yui-build yui-build-docker release release-docker

yui-build:
	cd yui/src && shifter --recursive --no-lint

yui-build-docker:
	docker build -t $(DOCKER_IMAGE) .
	docker run --rm \
		-u "$(shell id -u)":"$(shell id -g)" \
		-e HOME=/tmp \
		-v "$(PWD)":/work \
		-w /work \
		$(DOCKER_IMAGE) \
		bash -lc 'cd yui/src && shifter --recursive --no-lint'

release:
	cd yui/src && shifter --recursive --no-lint
	$(PYTHON) utils/release.py -f

release-docker:
	docker build -t $(DOCKER_IMAGE) .
	docker run --rm \
		-u "$(shell id -u)":"$(shell id -g)" \
		-e HOME=/tmp \
		-v "$(PWD)":/work \
		-w /work \
		$(DOCKER_IMAGE) \
		bash -lc 'cd yui/src && shifter --recursive --no-lint && cd /work && python3 utils/release.py -f'
