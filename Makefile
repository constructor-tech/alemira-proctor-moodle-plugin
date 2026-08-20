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
#
# NTG-185: a module that is NOT an AMD module must not live under amd/src/ at all
# — keeping it off this list is not enough. Moodle scans amd/src/ itself when it
# assembles lib/amd/build/first.min.js, so anything sitting there is concatenated
# into that bundle whether or not this Makefile builds it. js/proctor.js is an ES
# module using a dynamic import(), which is invalid AMD syntax; while it was under
# amd/src/ it produced "Uncaught SyntaxError" plus "No define call for core/first"
# on EVERY page of the site, and since Moodle 4.x draws its navigation with that
# bundle, the primary navigation and user menu disappeared site-wide. It is served
# by a plain <script type="module"> tag from js/ instead — see launch.php.
AMD_MODULES := fader preset_edit

.PHONY: yui-build yui-build-docker amd-build release release-docker test lint

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

# Run the plugin's PHPUnit suite against a Moodle install.
#
# There is no Moodle in this repository, so MOODLE_ROOT must point at one whose
# PHPUnit environment has been initialised (admin/tool/phpunit/cli/init.php).
# The plugin is expected to be installed, or symlinked, at
# $(MOODLE_ROOT)/availability/condition/proctor.
#
#   make test                        # whole suite
#   make test MOODLE_ROOT=/path/to/moodle
#   make test FILTER=test_save_round_trips_groups
MOODLE_ROOT ?= ../../..
FILTER ?=

test:
	@test -x "$(MOODLE_ROOT)/vendor/bin/phpunit" || { \
		echo "No PHPUnit at $(MOODLE_ROOT)/vendor/bin/phpunit."; \
		echo "Set MOODLE_ROOT to a Moodle install with PHPUnit initialised."; \
		exit 1; }
	@for f in tests/*_test.php; do \
		echo "== $$f"; \
		( cd "$(MOODLE_ROOT)" && vendor/bin/phpunit --no-coverage \
			$(if $(FILTER),--filter $(FILTER),) \
			availability/condition/proctor/$$f ) || exit 1; \
	done

# Syntax-check every PHP file the release ships.
lint:
	@find . -name '*.php' \
		-not -path './node_modules/*' -not -path './releases/*' \
		-print0 | xargs -0 -n1 php -l > /dev/null && echo "PHP syntax OK"
	@node --check yui/src/form/js/form.js && echo "form.js syntax OK"
