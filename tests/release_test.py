"""Regression checks for branded release archives."""

import json
import pathlib
import re
import shutil
import subprocess
import sys
import tempfile
import unittest
import zipfile


class ReleaseTest(unittest.TestCase):
    def test_branded_build_keeps_the_shared_sdk_url(self):
        source = pathlib.Path(__file__).resolve().parents[1]
        with tempfile.TemporaryDirectory() as directory:
            root = pathlib.Path(directory)
            for name in ('utils/release.py', 'version.php', 'classes/launcher.php'):
                target = root / name
                target.parent.mkdir(parents=True, exist_ok=True)
                shutil.copyfile(source / name, target)
            brand = root / 'branding/sample'
            brand.mkdir(parents=True)
            (brand / 'brand.json').write_text(json.dumps({
                'frankenstyle': 'sample',
                'display_name': 'Sample',
                'code_patterns': {'proctor': 'sample', 'sampleing': 'proctoring'},
                'text_patterns': {},
            }))
            subprocess.run(
                [sys.executable, 'utils/release.py', '--brand', 'sample'],
                cwd=root, check=True, capture_output=True, text=True,
            )
            archive = next((root / 'releases').glob('sample-*.zip'))
            with zipfile.ZipFile(archive) as package:
                launcher = package.read('sample/classes/launcher.php').decode()
                original = (source / 'classes/launcher.php').read_text()
                pattern = r"const SDK_MODULE_URL = '([^']+)'"
                self.assertEqual(re.search(pattern, original).group(1),
                                 re.search(pattern, launcher).group(1))
                self.assertIn('namespace availability_sample;', launcher)
                self.assertIn("'availability_sample'",
                              package.read('sample/version.php').decode())


if __name__ == '__main__':
    unittest.main()
