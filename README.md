# PROCTOR MOODLE PLUGIN

## Requirements

Proctor by Constructor plugin was tested with Moodle versions 3.8 to 4.1.

## Installation and integration

### Plugin installation

Download ZIP file from (add link), login to your Moodle site as an admin, open `Site administration → Plugins → Install plugins`, 
upload the ZIP file and install it.

### Prerequisites
* If using `apache2 + mod_php` configuration, make sure that `mod_rewrite` is enabled and and overrides are allowed for the plugin dir. 
  if it is impossible to to enable overrides, refer to contents of `.htaccess` and implement this configuration in your `VirtualHosts` config.


### Integration setup

1. Request integration name, secret, account name and id form Constructor Proctor team.
2. Go to `Admin -> Plugins -> Constructor Proctor -> Integration Settings` and fill these values.
3. Choose value for `Send user emails to Constructor Proctor` field, if disabled, Proctor will not receive emails of learners.
4. Choose value for `Seemless authorization`, if enabled, Proctor will be able to authorize learners into moodle, 
   if disabled, users will have to login into modle inside proctoring.

## Usage

### Setting a restriction for a module

1. In course editing mode, choose `Edit settings` for the module (quiz) you want to use with Proctor by Constructor proctoring. 
   Scroll down to `Restrict access`.
2. Choose `Add restrictions... → Proctor by Constructor` to enable proctoring for this module.
3. Specify the duration of the proctoring session. If you already have a time restriction for the module (quiz), 
   the proctoring session duration must be equal to the time restriction setting.
4. Choose the proctoring mode.
5. Choose the rules for the proctoring session.


### Adding a new entry

If the student attempted the module once, for every following attempt a new Proctor by Constructor entry must be created in the following way.
1. Login as an admin. Go to `Site administration → Reports → Proctor by Constructor settings`.
2. Find the exam you want to allow a new attempt for. Click the button `New entry`.


### Special accomodation field
The plugin allows passing learner's special accommodation to Constuctor Proctor, a custom profile field has to be created for that.
1. Go to `Admin -> Users -> User profile fields`
2. Click `Create a new profile field -> Text area`
3. Set short name to `proctor_special_accommodations`, set name to something readable, e.g. "Proctoring special accommodations"


## Structure

### Entrypoints
 
* `index.php` - List of student `entries` or proctoring sessions in Admin panel
* `defaults.php` - Admin section for changing default proctored exam settings
* `api.php` - Webhook implementing Proctor Simple API - receives information on session status changes and echeduled exams
* `entry.php` - Learner is redirected here inside proctoring. Handles seemless auths and redirects to quiz

### Local development

* Install moodle by following the official [installation guide](https://docs.moodle.org/500/en/Installing_Moodle). 
  Use a PHP version required for your Moodle version of choice. The setup process will guide you through additional requirements.
* Clone this repository into `[moodle_dir]/availability/condition/proctor`
* Enable Developer mode and debug messages output in `Site administration → Development`
* For rebuilding the frontend part, you have to use YUI and Shifter, see the [official guide](https://moodledev.io/docs/5.0/guides/javascript/yui)
