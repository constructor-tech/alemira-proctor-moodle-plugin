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

def increment_version():
    """Increment version number by 1 in version.php"""
    content = open('version.php', 'r').read()
    version_regex = r'(plugin->version\s*=\s*)(\d+)'
    version_match = re.search(version_regex, content)
    if not version_match:
        print("Can't find version in version.php")
        sys.exit(1)
    
    current_version = int(version_match.group(2))
    new_version = current_version + 1
    
    new_content = re.sub(version_regex, f'\\g<1>{new_version}', content)
    
    with open('version.php', 'w') as f:
        f.write(new_content)
    
    print(f"Version incremented: {current_version} -> {new_version}")
    return str(new_version)

def check_version_exists(version, name='proctor'):
    """Check if archive with this version already exists"""
    archive_path = f'releases/{name}-{version}.zip'
    return os.path.exists(archive_path)

def ask_yes_no(question, default='y'):
    """Ask user yes/no question with default answer"""
    valid = {"yes": True, "y": True, "ye": True, "no": False, "n": False}
    if default == 'y':
        prompt = " [Y/n] "
        default_answer = True
    else:
        prompt = " [y/N] "
        default_answer = False
    
    # Check if we're running in non-interactive mode (no TTY)
    if not sys.stdin.isatty():
        print(question + prompt + f"(auto-answer: {'yes' if default_answer else 'no'})")
        return default_answer
    
    while True:
        print(question + prompt, end='')
        try:
            choice = input().lower().strip()
        except EOFError:
            # Handle non-interactive mode
            print(f"(auto-answer: {'yes' if default_answer else 'no'})")
            return default_answer
        
        if choice == '':
            return default_answer
        elif choice in valid:
            return valid[choice]
        else:
            print("Please respond with 'yes' or 'no' (or 'y' or 'n').")


def run(name='proctor', dry=False, verbose=False, force=False):
    # List project files
    ignore_files = [ 'releases', 'utils', 'node_modules', '.git']
    append_files = ['.htaccess',]

    output_dir = f'releases/{name}/'
    
    # Track version increment
    version_incremented = False
    old_version = None

    # Check if output_dir exists and ask to remove it
    if os.path.exists(output_dir):
        if ask_yes_no(f'Output directory {output_dir} exists. Remove it?', default='y'):
            print(f'Removing output dir {output_dir}')
            shutil.rmtree(output_dir)
        else:
            print('Cancelled by user')
            sys.exit(0)

    version = get_version()
    
    # Check if version already exists
    if check_version_exists(version, name):
        print(f'Archive {name}-{version}.zip already exists in releases/')
        if ask_yes_no('Increment version and rebuild?', default='y'):
            old_version = version
            version = increment_version()
            version_incremented = True
            print(f'New version: {version}')
        else:
            print('Cancelled by user')
            sys.exit(0)
    
    archive_name = f'{name}-{version}.zip'

    old_name = 'proctor'
    code_patterns = {}
    text_patterns = {}
    rename = False

    if name == 'examus2':
        code_patterns = {
            'use_proctor': 'use_examus2',
            'proctor_url': 'examus_url',
            'proctorurl': 'examusurl',
            'PROCTOR': 'EXAMUS2',
            'proctor': 'examus2',
            'examus2ing': 'proctoring',
        }
        text_patterns = {
            'Proctor': 'Examus',
            'Proctor': 'Examus',
            ' by Constructor': '',
        }
        rename = True

    files = []
    for filename in glob.iglob('**/*', recursive=True):
        if not filename.startswith(tuple(ignore_files)):
          files.append(filename)

    files = files + append_files

    if verbose:
      print("File list:")
      pp(files)

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
                try:
                    content = f.read()
                except UnicodeDecodeError as e:
                    print(f'[release.py] UnicodeDecodeError while reading file as text: {filename}')
                    raise

            content = replace(content, code_patterns)
            content = replace(content, text_patterns)

            with open(newname, 'w+') as f:
                f.write(content)
        else:
            os.makedirs(newname, exist_ok=True)

    print(f'Creating archive {archive_name}')
    os.chdir('releases')
    os.system(f'zip -r "{archive_name}" {name}')
    os.chdir('..')
    
    # Generate release report
    print('\n' + '='*60)
    print('RELEASE REPORT')
    print('='*60)
    print(f'Plugin name:      {name}')
    
    if version_incremented:
        print(f'Version:          {old_version} → {version} (incremented)')
    else:
        print(f'Version:          {version}')
    
    print(f'Archive:          releases/{archive_name}')
    
    # Get archive size
    archive_path = f'releases/{archive_name}'
    if os.path.exists(archive_path):
        size_bytes = os.path.getsize(archive_path)
        size_kb = size_bytes / 1024
        print(f'Archive size:     {size_kb:.1f} KB ({size_bytes:,} bytes)')
    
    # Count files in the release
    file_count = len([f for f in files if os.path.isfile(f)])
    dir_count = len([f for f in files if os.path.isdir(f)])
    print(f'Files included:   {file_count}')
    print(f'Directories:      {dir_count}')
    
    print('='*60)
    print('✓ Release completed successfully!')
    print('='*60)



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
