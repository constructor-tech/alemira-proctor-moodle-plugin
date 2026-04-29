# Moodle Plugin Certification Review

Perform a comprehensive certification review of this Moodle plugin against the official Moodle Plugin Directory requirements. Check every criterion below and produce a structured report.

## Plugin context

- **Component:** `availability_proctor` (availability condition plugin)
- **version.php** at repo root
- **Key directories:** `classes/`, `db/`, `lang/`, `templates/`, `yui/`

---

## Certification checklist

For each section, report: PASS, FAIL, or WARNING with a brief explanation.

### 1. Plugin structure and metadata

- [ ] `version.php` exists and contains: `$plugin->component`, `$plugin->version` (YYYYMMDDXX), `$plugin->requires`, `$plugin->maturity`, `$plugin->release`
- [ ] Component name matches directory path convention (`availability_proctor`)
- [ ] GPL v3 license header present in ALL `.php` files (check every single one)
- [ ] `defined('MOODLE_INTERNAL') || die();` guard present where required (all included files, NOT entry points)
- [ ] Entry point scripts (`api.php`, `entry.php`, `index.php`, `presets.php`, `preset_edit.php`, `defaults.php`, `ajax_preset.php`) use `require_once(__DIR__ . '/../../config.php')` or equivalent — NOT the `MOODLE_INTERNAL` guard

### 2. Coding standards (Moodle PHP)

- [ ] Files use `<?php` opening tag (no short tags, no closing `?>` tag)
- [ ] Indentation uses 4 spaces (not tabs)
- [ ] Line length does not exceed 180 characters (warn at 132)
- [ ] Class names follow Moodle autoloading (`\availability_proctor\classname`)
- [ ] Function/method names use `snake_case`
- [ ] Constants use `UPPER_CASE`
- [ ] No use of deprecated PHP functions or Moodle APIs
- [ ] Proper use of Moodle string concatenation (`.` operator spacing)

### 3. PHPDoc and documentation

- [ ] Every class has a `@package`, `@copyright`, `@license` docblock
- [ ] Every public method has a PHPDoc block with `@param` and `@return`
- [ ] File-level docblocks present with `@package` tag
- [ ] `@package` value is `availability_proctor` everywhere

### 4. Language strings

- [ ] All user-visible strings use `get_string()` — no hardcoded English in output
- [ ] `lang/en/availability_proctor.php` exists and is the canonical source
- [ ] Every string key used in code exists in the lang file
- [ ] Every string key in lang file is actually used in code (no orphaned strings)
- [ ] `$string['pluginname']` is defined
- [ ] Lang file has proper GPL header and `defined('MOODLE_INTERNAL')` guard

### 5. Capabilities and access control

- [ ] `db/access.php` defines capabilities with proper structure
- [ ] Every page/endpoint checks capabilities with `require_capability()` or `has_capability()`
- [ ] Capabilities use correct `contextlevel` (CONTEXT_SYSTEM, CONTEXT_COURSE, etc.)
- [ ] `archetypes` are sensible for the capability purpose
- [ ] No capability grants to `guest` or `user` archetypes without strong justification

### 6. Database schema

- [ ] `db/install.xml` is valid XMLDB format
- [ ] Table names are prefixed with plugin component (`availability_proctor_*`)
- [ ] All fields have explicit `XMLDB_TYPE_*`, length, null, default
- [ ] Primary keys are defined
- [ ] `db/upgrade.php` exists if schema changed post-initial release and uses proper `xmldb_*` API
- [ ] `$plugin->version` is bumped when schema changes

### 7. Security

- [ ] All forms check `sesskey` (via `require_sesskey()` or form API)
- [ ] SQL queries use parameterized queries (`$DB->get_record()`, etc.) — no string interpolation in SQL
- [ ] User input is cleaned: `required_param()` / `optional_param()` with proper `PARAM_*` types
- [ ] Output is escaped: `format_string()`, `format_text()`, `s()`, or `html_writer`
- [ ] No use of `$_GET`, `$_POST`, `$_REQUEST`, `$_SERVER` directly — use Moodle param API
- [ ] AJAX endpoints validate sesskey or use proper WS token auth
- [ ] `require_login()` called where appropriate
- [ ] No `eval()`, `exec()`, `shell_exec()`, `system()`, `passthru()`, or `preg_replace()` with `e` modifier
- [ ] File includes use absolute paths or Moodle's autoloader (no user-controlled includes)

### 8. Privacy API (GDPR)

- [ ] `classes/privacy/provider.php` exists
- [ ] Implements `\core_privacy\local\metadata\provider` if storing user data
- [ ] Implements `\core_privacy\local\request\plugin\provider` for export/delete
- [ ] All user data tables are declared in `get_metadata()`
- [ ] Export and delete handlers cover all user data

### 9. Event observers

- [ ] `db/events.php` registers observers correctly
- [ ] Observer callbacks exist and match registered class/method
- [ ] Events are standard Moodle events (not custom undefined ones)

### 10. JavaScript

- [ ] JS follows Moodle module pattern (YUI or AMD)
- [ ] No inline JavaScript in PHP files (use `$PAGE->requires->js_*` or YUI modules)
- [ ] No jQuery loaded outside of Moodle's jQuery wrapper
- [ ] YUI modules have proper `build.json` and `meta/*.json`

### 11. Settings and configuration

- [ ] `settings.php` uses `$ADMIN->add()` and proper `admin_setting_*` classes
- [ ] Setting names are prefixed with component
- [ ] Sensitive settings (passwords, secrets) use `admin_setting_configpasswordunmask`
- [ ] Default values are sensible

### 12. Output and rendering

- [ ] Uses Moodle output API (`$OUTPUT`, renderers, templates) — not raw `echo`
- [ ] HTML is generated through `html_writer` or templates
- [ ] CSS is loaded via `$PAGE->requires->css()` or plugin styles
- [ ] No inline styles in PHP output where avoidable

---

## Report format

Produce the report as a markdown table:

| # | Category | Status | Issues |
|---|----------|--------|--------|
| 1 | Plugin structure | PASS/FAIL/WARN | Details... |
| ... | ... | ... | ... |

Then list:
1. **Critical issues** (FAIL) that MUST be fixed before submission
2. **Warnings** that SHOULD be fixed but won't block certification
3. **Recommendations** for best practices

For each issue, reference the specific file and line number.
