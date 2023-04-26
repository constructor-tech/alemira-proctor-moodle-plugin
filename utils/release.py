#!/bin/env python

import getopt, sys, glob, shutil, os, re
from pprint import pp


argumentList = sys.argv[1:]
options = "hdfvn:"
long_options = ['help', 'dry', 'force', 'verbose', 'name']

def replace(string, replacements):
    for word, replacement in replacements.items():
        string = string.replace(word, replacement)
    return string

def display_help():
    print('Arguments:')
    print('  -v --verbose')
    print('  -f --force')
    print('  -d --dry')
    print('  -n NAME --name NAME where name is "alemira" or "examus2", default is "alemira"')

def get_version():
    content = open('version.php', 'r').read()
    version_regex = r'plugin->version\s*=\s*(\d+)'
    version_match = re.search(version_regex, content)
    if not version_match:
        print("Cant find version in version.php")
        os.exit(1)
    return version_match.group(1)


def run(name='proctor', dry=False, verbose=False, force=False):
    # List project files
    ignore_files = [ 'releases', 'utils',]
    append_files = ['.htaccess',]

    output_dir = f'releases/{name}/'

    version = get_version()
    archive_name = f'{name}-{version}.zip'

    old_name = 'alemira'
    code_patterns = {}
    text_patterns = {}
    rename = False

    if name == 'examus2':
        code_patterns = {
            'use_alemira': 'use_examus',
            'alemira_url': 'examus_url',
            'alemiraurl': 'examusurl',
            'ALEMIRA': 'EXAMUS2',
            'alemira': 'examus2',
        }
        text_patterns = {
            'Alemira': 'Examus',
            'alemira': 'Examus',
        }
        rename = True

    if name == 'alemira':
        exit;
        #rename = True

    files = []
    for filename in glob.iglob('**/*', recursive=True):
        if not filename.startswith(tuple(ignore_files)):
          files.append(filename)

    files = files + append_files

    if verbose:
      print("File list:")
      pp(files)

    if os.path.exists(output_dir):
        print(f'Output dir {output_dir} already exists')
        if force:
            print(f'Claning output dir {output_dir}')
            shutil.rmtree(output_dir)
        else:
            sys.exit(1)

    if rename:
        print(f"Renaming {old_name} to {name}")
    else:
        print("Skiping renames")

    print(f'Creating output dir {output_dir}')
    os.makedirs(output_dir, exist_ok=True)

    for filename in files:
        newname = replace(filename, code_patterns)
        if newname != filename:
            print(f'Renaming {filename} to {newname}')
        elif verbose:
            print(f'Processing: {newname}')

        newname = f'{output_dir}{newname}'

        if os.path.isfile(filename):
            with open(filename) as f:
                content = f.read()

            content = replace(content, code_patterns)
            content = replace(content, text_patterns)

            with open(newname, 'w+') as f:
                f.write(content)
        else:
            os.makedirs(newname, exist_ok=True)

    print(f'Creating archive {archive_name}')
    os.chdir('releases')
    os.system(f'zip -r "{archive_name}" {name}')
    print('Finished')



def main():
    try:
        arguments, values = getopt.getopt(argumentList, options, long_options)
    except getopt.error as err:
        print (str(err))
        display_help()

    opts = {}
    for arg, value in arguments:
        if arg in ("-h", "--help"):
            display_help()
            sys.exit(2)
        elif arg in ("-d", "--dry"):
            opts['dry'] = True
        elif arg in ("-v", "--verbose"):
            opts['verbose'] = True
        elif arg in ("-f", "--forse"):
            opts['force'] = True
        elif arg in ("-n", "--name"):
            if value not in ['alemira', 'examus2']:
                print('Only "alemira" and "examus2" are supported')
            opts['name'] = value

    run(**opts)


if __name__ == "__main__":
    main()
