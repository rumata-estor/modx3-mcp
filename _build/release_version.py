"""Single target release identity for staging and CI (build-time only)."""
import json
import re
from pathlib import Path

def load_release(path=None):
    path = path or Path(__file__).with_name('release-version.json')
    data = json.loads(path.read_text(encoding='utf-8'))
    version, release = data['version'], data['release']
    if not isinstance(version, str) or not re.fullmatch(r'(?:0|[1-9][0-9]*)\.(?:0|[1-9][0-9]*)\.(?:0|[1-9][0-9]*)', version):
        raise ValueError('Invalid package version')
    if not isinstance(release, str) or not re.fullmatch(r'pl|(?:alpha|beta|rc)[1-9][0-9]*', release):
        raise ValueError('Invalid package release')
    node = version if release == 'pl' else version + '-' + re.sub(r'([a-z]+)([0-9]+)', r'\1.\2', release)
    return version, release, node

if __name__ == '__main__':
    version, release, node = load_release()
    print(f'RELEASE_VERSION={version}')
    print(f'RELEASE_CHANNEL={release}')
    print(f'RELEASE_ID={version}-{release}')
    print(f'NODE_RELEASE_VERSION={node}')
