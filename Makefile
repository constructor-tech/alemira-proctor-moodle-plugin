SHELL := /bin/bash
PYTHON ?= python3
DOCKER_IMAGE ?= proctor-moodle-plugin-release

# Prefer locally installed shifter (node_modules/.bin/shifter, populated by
# `npm install`) so `make release` works without Docker or a global install.
# Falls back to PATH-resolved `shifter` if no local one is found.
SHIFTER := $(shell test -x node_modules/.bin/shifter && echo node_modules/.bin/shifter || echo shifter)
TERSER := $(shell test -x node_modules/.bin/terser && echo node_modules/.bin/terser || echo terser)

# AMD modules under amd/src/, built to amd/build/<name>.min.js. Plain
# dependency-free ES5 modules — minification only, no Babel/transpile step
# needed (see amd/src/*.js).
AMD_MODULES := fader preset_edit

.PHONY: yui-build yui-build-docker amd-build release release-docker

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

amd-build:
	@for m in $(AMD_MODULES); do \
		$(TERSER) amd/src/$$m.js -c -m -o amd/build/$$m.min.js --source-map "url='$$m.min.js.map'"; \
	done

release: yui-build amd-build
	$(PYTHON) utils/release.py

release-docker:
	docker build -t $(DOCKER_IMAGE) .
	docker run --rm \
		-u "$(shell id -u)":"$(shell id -g)" \
		-e HOME=/tmp \
		-v "$(PWD)":/work \
		-w /work \
		$(DOCKER_IMAGE) \
		bash -lc 'cd yui/src && shifter --recursive --no-lint && cd /work \
			&& for m in $(AMD_MODULES); do npx terser amd/src/$$m.js -c -m -o amd/build/$$m.min.js --source-map "url=$$m.min.js.map"; done \
			&& python3 utils/release.py'
