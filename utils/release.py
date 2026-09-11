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
IGNORE_PATHS = [
    'releases',     # output artifacts
    'utils',        # this script
    'branding',     # per-brand overlay sources, applied separately
    'node_modules', '.git', '.cursor', '.claude', '.vscode', 'openspec',
]

# Files force-added in case some glob configurations skip dotfiles.
APPEND_FILES = ['.htaccess']

argument_list = sys.argv[1:]
short_options = 'hvb:'
long_options = ['help', 'verbose', 'brand=']


def replace(string, replacements):
    for word, replacement in replacements.items():
        string = string.replace(word, replacement)
    return string


def display_help():
    print('Usage: release.py [-v] [-b BRAND]')
    print('  -v --verbose          Verbose file-by-file output')
    print('  -b BRAND --brand BRAND  Build only this brand (default: build every brand)')
    print('Available brands: ' + ', '.join(BRANDS.keys()))


def get_version():
    with open('version.php', 'r', encoding='utf-8') as f:
        content = f.read()
    match = re.search(r'plugin->version\s*=\s*(\d+)', content)
    if not match:
        print("Can't find version in version.php")
        sys.exit(1)
    return match.group(1)


def increment_version():
    """Bump $plugin->version by 1 in version.php and return the new value as a string."""
    with open('version.php', 'r', encoding='utf-8') as f:
        content = f.read()
    pattern = r'(plugin->version\s*=\s*)(\d+)'
    match = re.search(pattern, content)
    if not match:
        print("Can't find version in version.php")
        sys.exit(1)
    new_version = int(match.group(2)) + 1
    new_content = re.sub(pattern, f'\\g<1>{new_version}', content)
    with open('version.php', 'w', encoding='utf-8') as f:
        f.write(new_content)
    print(f"Version incremented: {match.group(2)} -> {new_version}")
    return str(new_version)


def archive_path(brand_id, version):
    return f'releases/{brand_id}-{version}.zip'


def ask_yes_no(question, default='y'):
    valid = {'yes': True, 'y': True, 'ye': True, 'no': False, 'n': False}
    default_answer = (default == 'y')
    prompt = ' [Y/n] ' if default_answer else ' [y/N] '

    if not sys.stdin.isatty():
        print(question + prompt + f"(auto-answer: {'yes' if default_answer else 'no'})")
        return default_answer

    while True:
        print(question + prompt, end='')
        try:
            choice = input().lower().strip()
        except EOFError:
            print(f"(auto-answer: {'yes' if default_answer else 'no'})")
            return default_answer
        if choice == '':
            return default_answer
        if choice in valid:
            return valid[choice]
        print("Please respond with 'yes' or 'no' (or 'y' or 'n').")


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
    text = replace(text, code_patterns)
    text = replace(text, text_patterns)
    with open(dest_path, 'wb') as f:
        f.write(text.encode('utf-8'))
    if verbose:
        print(f'  staged {src_path} -> {dest_path}')


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

    size_kb = os.path.getsize(artifact) / 1024
    print(f'  artifact: {artifact} ({size_kb:.1f} KB)')
    return artifact


def run(only_brand=None, verbose=False):
    brands_to_build = list(BRANDS.keys())
    if only_brand is not None:
        if only_brand not in BRANDS:
            print(f"Unknown brand '{only_brand}'. Available: {', '.join(BRANDS.keys())}")
            sys.exit(2)
        brands_to_build = [only_brand]

    version = get_version()

    existing = [b for b in brands_to_build if os.path.exists(archive_path(b, version))]
    if existing:
        print(f"Archives already exist at version {version} for: {', '.join(existing)}")
        if ask_yes_no('Increment version and rebuild all selected brands?', default='y'):
            version = increment_version()
        else:
            print('Cancelled by user')
            sys.exit(0)

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
        elif arg in ('-b', '--brand'):
            opts['only_brand'] = value

    run(**opts)


if __name__ == '__main__':
    main()
