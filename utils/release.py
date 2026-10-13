#!/bin/env python

"""Build one zip per brand listed in BRANDS.

Each brand entry describes:
- frankenstyle:    Moodle plugin component suffix (availability_<x>). Must be
                   unique across brands so multiple builds can coexist on the
                   same Moodle install. Also becomes the in-zip top-level dir.
- display_name:    Human-readable plugin name (admin/UI surfaces).
- code_patterns:   Literal substitutions applied to file *names* AND *contents*.
                   Order matters: dict insertion order is preserved and each
                   pass operates on the previous result. Put longer / more
                   specific keys first; put any "restore" rules last.
- text_patterns:   Substitutions applied only to file contents. Use for
                   display strings that must not leak through to the
                   rebranded UI.

To add a new brand: add an entry here. If the brand needs different runtime
behaviour (e.g. preset seeding profile), also add an overlay at
branding/<brand>/classes/brand.php overriding the default brand identity.
"""

import getopt
import glob
import json
import os
import re
import shutil
import sys
import zipfile
from pprint import pp


# Constructor is the canonical source-of-truth brand: the repo is authored
# as the Constructor build, so it needs no overlay or substitutions.
# Other brands live in branding/<brand>/ — each with a brand.json defining
# its config, and optional overlay files (classes/brand.php, classes/preset_seed.php,
# lang/..., etc.). discover_brands() picks them up automatically.
CONSTRUCTOR = {
    'frankenstyle': 'proctor',
    'display_name': 'Constructor Proctor',
    'code_patterns': {},
    'text_patterns': {},
}


def discover_brands():
    """Build the brand registry: Constructor (default) plus every
    branding/<name>/brand.json found on disk.

    Each brand.json must provide: frankenstyle (string), display_name (string),
    code_patterns (object), text_patterns (object). Insertion order in
    code_patterns is preserved (load with object_pairs_hook=dict, which keeps
    insertion order in Python 3.7+).
    """
    brands = {'constructor': dict(CONSTRUCTOR)}
    if not os.path.isdir('branding'):
        return brands
    for entry in sorted(os.listdir('branding')):
        config_path = os.path.join('branding', entry, 'brand.json')
        if not os.path.isfile(config_path):
            continue
        with open(config_path, encoding='utf-8') as f:
            config = json.load(f)
        # Validate required keys; missing ones are a build-time bug.
        for required in ('frankenstyle', 'display_name', 'code_patterns', 'text_patterns'):
            if required not in config:
                print(f"branding/{entry}/brand.json is missing required key '{required}'")
                sys.exit(1)
        brands[entry] = config
    return brands


BRANDS = discover_brands()

# Top-level paths excluded from every build (prefix match on glob output).
#
# These are internal or build-only: nothing here is needed by a customer or a
# Moodle Marketplace reviewer. Some are gitignored, but release.py walks the
# working tree rather than the Git index, so every local-only path must also be
# excluded here explicitly.
#
# Entries are PREFIXES (see collect_source_files), which is deliberate for
# 'CLAUDE': the list once named 'CLAUDE.md' exactly, the file was later renamed
# to CLAUDE.local.md, and every build from then on shipped it again. A prefix
# catches any future variant without anyone having to remember this.
IGNORE_PATHS = [
    'releases',     # output artifacts
    'utils',        # this script
    'ci',           # moodle-plugin-ci container — build tooling, not plugin code
    'dev',          # local Moodle dev stand (docker compose) — build tooling
    'tests',        # PHPUnit source tests — kept in Git, not needed at runtime
    'branding',     # per-brand overlay sources, applied separately
    'plans',        # internal planning notes — must not ship
    'mylocal',      # local references, TODOs and copied service documentation
    'AGENTS',       # repository AI context, tracked but not part of the plugin
    'CLAUDE',       # prefix: CLAUDE.md, CLAUDE.local.md, any variant
    'Dockerfile',   # build container, not Moodle runtime
    'Makefile',     # local build/test commands
    'package.json', 'package-lock.json',  # local JavaScript toolchain
    'vendor', 'coverage', '.nyc_output', '.phpunit.cache',
    'composer.lock', '.env', '.editorconfig', '.idea',
    'yui/build/moodle-availability_proctor-form/moodle-availability_proctor-form-coverage.js',
    'node_modules', '.git', '.cursor', '.claude', '.vscode', 'openspec',
]

# A second, archive-level guard. The source filter above keeps builds small; this
# list makes a release fail closed if a future packaging change or brand overlay
# manages to reintroduce an internal path.
FORBIDDEN_ARCHIVE_PATHS = (
    'AGENTS.md',
    'Dockerfile',
    'Makefile',
    'package.json',
    'package-lock.json',
    'composer.lock',
    'vendor/',
    'coverage/',
    '.nyc_output/',
    '.phpunit.cache/',
    'mylocal/',
    'ci/',
    'dev/',
    'tests/',
    'branding/',
    'plans/',
    'utils/',
    '.claude/',
    '.cursor/',
    '.git/',
    '.github/',
    '.idea/',
    '.vscode/',
    'openspec/',
    'yui/build/moodle-availability_proctor-form/moodle-availability_proctor-form-coverage.js',
)

FORBIDDEN_ARCHIVE_NAMES = (
    '.DS_Store',
    'Thumbs.db',
    '.env',
    '.editorconfig',
    '.gitignore',
    '.phpunit.result.cache',
)

FORBIDDEN_ARCHIVE_NAME_PREFIXES = ('.env.',)
FORBIDDEN_ARCHIVE_NAME_SUFFIXES = ('-coverage.js', '.pyc', '.pyo', '.pyd', '.swp', '.swo')

# Files force-added in case some glob configurations skip dotfiles.
APPEND_FILES = ['.htaccess']

argument_list = sys.argv[1:]
short_options = 'hvfb:'
long_options = ['help', 'verbose', 'force', 'brand=']


def replace(string, replacements):
    for word, replacement in replacements.items():
        string = string.replace(word, replacement)
    return string


def display_help():
    print('Usage: release.py [-v] [-f] [-b BRAND]')
    print('  -v --verbose          Verbose file-by-file output')
    print('  -f --force            Overwrite an archive that already exists for this version')
    print('  -b BRAND --brand BRAND  Build only this brand (default: build every brand)')
    print('Available brands: ' + ', '.join(BRANDS.keys()))
    print('')
    print('The version comes from version.php and is never modified by this script.')
    print('Bump it yourself before cutting a new release.')


def get_version():
    with open('version.php', 'r', encoding='utf-8') as f:
        content = f.read()
    match = re.search(r'plugin->version\s*=\s*(\d+)', content)
    if not match:
        print("Can't find version in version.php")
        sys.exit(1)
    return match.group(1)


def archive_path(brand_id, version):
    return f'releases/{brand_id}-{version}.zip'


def collect_source_files():
    """Walk the working tree once; returns paths to copy into every brand build."""
    files = []
    for filename in glob.iglob('**/*', recursive=True):
        if not filename.startswith(tuple(IGNORE_PATHS)):
            files.append(filename)
    for forced in APPEND_FILES:
        if os.path.exists(forced) and forced not in files:
            files.append(forced)
    return files


def collect_overlay_files(brand_id):
    """Walk branding/<brand>/ recursively, returning paths relative to that root.
    brand.json is build-time config; it does not get staged into the plugin tree."""
    root = os.path.join('branding', brand_id)
    if not os.path.isdir(root):
        return []
    out = []
    for filename in glob.iglob(os.path.join(root, '**', '*'), recursive=True):
        rel = os.path.relpath(filename, root).replace(os.sep, '/')
        if rel == 'brand.json':
            continue
        out.append((filename, rel))
    return out


def stage_entry(src_path, dest_path, code_patterns, text_patterns, verbose):
    """Stage a single file or directory into dest_path, applying substitutions."""
    if os.path.isdir(src_path):
        os.makedirs(dest_path, exist_ok=True)
        return
    if not os.path.isfile(src_path):
        return
    os.makedirs(os.path.dirname(dest_path), exist_ok=True)
    # Read/write as bytes to preserve original line endings and to handle any
    # non-UTF8 fallthrough loudly rather than silently re-encoding.
    with open(src_path, 'rb') as f:
        raw = f.read()
    try:
        text = raw.decode('utf-8')
    except UnicodeDecodeError:
        print(f'[release.py] Non-UTF8 file copied byte-for-byte: {src_path}')
        with open(dest_path, 'wb') as f:
            f.write(raw)
        return
    # All brands load the same SDK; its public URL is not a brand identifier.
    sdk_url = 'https://sdk.web.proctor.constructor.app/proctoring.js'
    text = sdk_url.join(
        replace(replace(part, code_patterns), text_patterns)
        for part in text.split(sdk_url)
    )
    with open(dest_path, 'wb') as f:
        f.write(text.encode('utf-8'))
    if verbose:
        print(f'  staged {src_path} -> {dest_path}')


def audit_archive(artifact, top_level):
    """Fail when an archive escapes its plugin root or contains internal files."""
    root = f'{top_level}/'
    invalid = []

    with zipfile.ZipFile(artifact, 'r') as zf:
        for name in zf.namelist():
            if not name.startswith(root):
                invalid.append(name)
                continue

            relative = name[len(root):]
            parts = relative.split('/')
            basename = os.path.basename(relative)
            if (
                basename in FORBIDDEN_ARCHIVE_NAMES
                or basename.startswith(FORBIDDEN_ARCHIVE_NAME_PREFIXES)
                or basename.endswith(FORBIDDEN_ARCHIVE_NAME_SUFFIXES)
                or '__pycache__' in parts
            ):
                invalid.append(name)
                continue

            for forbidden in FORBIDDEN_ARCHIVE_PATHS:
                if relative == forbidden.rstrip('/') or relative.startswith(forbidden):
                    invalid.append(name)
                    break

    if invalid:
        os.remove(artifact)
        print(f'Release audit failed for {artifact}:')
        for name in invalid:
            print(f'  forbidden: {name}')
        sys.exit(1)


def build_brand(brand_id, brand_config, version, source_files, verbose):
    frankenstyle = brand_config['frankenstyle']
    code_patterns = brand_config['code_patterns']
    text_patterns = brand_config['text_patterns']

    stage_root = f'releases/{frankenstyle}'
    if os.path.exists(stage_root):
        print(f'Removing stale staging dir {stage_root}/')
        shutil.rmtree(stage_root)
    os.makedirs(stage_root, exist_ok=True)

    print(f"\n=== Building '{brand_id}' (availability_{frankenstyle}) ===")

    # 1. Source tree.
    for filename in source_files:
        rel = filename.replace(os.sep, '/')
        newrel = replace(rel, code_patterns)
        dest = f'{stage_root}/{newrel}'
        stage_entry(filename, dest, code_patterns, text_patterns, verbose)

    # 2. Brand-specific overlay (overrides any same-path source file).
    for src, rel in collect_overlay_files(brand_id):
        newrel = replace(rel, code_patterns)
        dest = f'{stage_root}/{newrel}'
        stage_entry(src, dest, code_patterns, text_patterns, verbose)
        print(f'  overlay: {rel} -> {newrel}')

    # 3. Zip from inside releases/ so the archive's top-level entry is just
    #    the frankenstyle dir — Moodle expects <frankenstyle>/version.php etc.
    artifact = archive_path(brand_id, version)
    if os.path.exists(artifact):
        os.remove(artifact)
    print(f'Creating archive {artifact}')
    # Pure-Python zipping so we don't depend on a `zip` binary on PATH —
    # works natively on Windows / macOS / Linux without Docker.
    # arcname is relative to releases/ so the archive's top-level entry is
    # the frankenstyle directory (Moodle install convention).
    with zipfile.ZipFile(artifact, 'w', zipfile.ZIP_DEFLATED) as zf:
        for root, dirs, files in os.walk(stage_root):
            for name in files:
                full = os.path.join(root, name)
                arcname = os.path.relpath(full, 'releases').replace(os.sep, '/')
                zf.write(full, arcname)

    audit_archive(artifact, frankenstyle)

    size_kb = os.path.getsize(artifact) / 1024
    print(f'  artifact: {artifact} ({size_kb:.1f} KB)')
    return artifact


def run(only_brand=None, verbose=False, force=False):
    brands_to_build = list(BRANDS.keys())
    if only_brand is not None:
        if only_brand not in BRANDS:
            print(f"Unknown brand '{only_brand}'. Available: {', '.join(BRANDS.keys())}")
            sys.exit(2)
        brands_to_build = [only_brand]

    version = get_version()

    # An archive already at this version means either version.php was not bumped
    # or the same version is being rebuilt on purpose. Both are the operator's
    # call, so stop and say so rather than deciding.
    #
    # This script used to bump version.php itself here, gated on a yes/no prompt
    # that auto-answered yes when stdin was not a terminal -- so any scripted or
    # piped build silently rewrote a tracked file. In Moodle $plugin->version is
    # the upgrade ladder: a phantom bump that gets committed makes every site run
    # an empty upgrade and burns a number a real savepoint can no longer use.
    existing = [b for b in brands_to_build if os.path.exists(archive_path(b, version))]
    if existing and not force:
        print(f"An archive already exists at version {version} for: {', '.join(existing)}")
        print('')
        print('  version.php is left alone by this script. Either bump')
        print(f"  $plugin->version past {version} and run again, or pass --force to")
        print('  overwrite the existing archive at the same version.')
        sys.exit(1)

    source_files = collect_source_files()
    if verbose:
        print('Source file list:')
        pp(source_files)

    artifacts = []
    for brand_id in brands_to_build:
        artifact = build_brand(brand_id, BRANDS[brand_id], version, source_files, verbose)
        artifacts.append((brand_id, artifact))

    print('\n' + '=' * 60)
    print('RELEASE REPORT')
    print('=' * 60)
    print(f'Version: {version}')
    print('Artifacts:')
    for brand_id, artifact in artifacts:
        size_kb = os.path.getsize(artifact) / 1024
        print(f'  {brand_id:14s} -> {artifact} ({size_kb:.1f} KB)')
    print('=' * 60)


def main():
    try:
        arguments, _values = getopt.getopt(argument_list, short_options, long_options)
    except getopt.error as err:
        print(str(err))
        display_help()
        sys.exit(2)

    opts = {}
    for arg, value in arguments:
        if arg in ('-h', '--help'):
            display_help()
            sys.exit(0)
        elif arg in ('-v', '--verbose'):
            opts['verbose'] = True
        elif arg in ('-f', '--force'):
            opts['force'] = True
        elif arg in ('-b', '--brand'):
            opts['only_brand'] = value

    run(**opts)


if __name__ == '__main__':
    main()
