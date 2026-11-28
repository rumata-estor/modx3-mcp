# Candidate release identity

Edit only `_build/release-version.json` to select the next candidate.
For example, `{"version":"1.2.8","release":"beta6"}` yields MODX
`1.2.8-beta6` and npm `1.2.8-beta.6`.

Use `python3 _build/prepare-release.py --candidate --platform modx2 --output /tmp/modxmcp2-candidate`
(or `modx3`), then run the staged PHP transport builder on a disposable MODX.
The current candidate CI uses the same staging path and derives archive names,
installation signatures, and npm version from the manifest. Run
`python3 _build/test.release-version.py` to validate staging.
The connector's existing numeric VERSION API remains numeric for compatibility.
The default staging mode without --candidate retains stable source identity
for historical builds and tests. Never publish that default staging output as a candidate.
