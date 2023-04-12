# ALEMIRA MOODLE PLUGIN

## Requirements
Alemira plugin was tested with Moodle versions 3.6 to 4.0.

## Installation and integration

### Plugin installation
Download ZIP file from (add link), login to your Moodle site as an admin, open `Site administration → Plugins → Install plugins`, upload the ZIP file and install it.

### Integration setup
TODO

## Usage

### Setting a restriction for a module
1. In course editing mode, choose `Edit settings` for the module (quiz) you want to use with Alemira proctoring. Scroll down to `Restrict access`.
2. Choose `Add restrictions... → Alemira` to enable proctoring for this module.
3. Specify the duration of the proctoring session. If you already have a time restriction for the module (quiz), the proctoring session duration must be equal to the time restriction setting.
4. Choose the proctoring mode.
5. Choose the rules for the proctoring session.

### Adding a new entry
If the student attempted the module once, for every following attempt a new Alemira entry must be created in the following way.
1. Login as an admin. Go to `Site administration → Reports → Alemira settings`.
2. Find the exam you want to allow a new attempt for. Click the button `New entry`.
