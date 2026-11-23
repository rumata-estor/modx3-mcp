# MODX MCP 1.2.8 Beta 1 — community test plan

**Status: candidate, not a stable release.** The MODX 2/3 transport archives and matching Node client are built on disposable CI instances. Nothing is automatically published to the official MODX Extras provider, and production deployments are out of scope.

> **Security principle:** We do not guarantee the mathematical absence of every conflict; we guarantee predictable system behavior and honest disclosure of its limitations.

Read the explicit [1.2.8 security limitations](SECURITY-LIMITATIONS-1.2.8.md) before testing.

## Candidate artifacts

| Target | Expected file |
| --- | --- |
| MODX 2.8.x | `modx2mcp-1.2.8-beta1.transport.zip` |
| MODX 3.x | `modx3mcp-1.2.8-beta1.transport.zip` |
| Matching Node MCP client (Node.js 18+) | `modx3-mcp-1.2.8-beta.1.tgz` |

The [Beta verification workflow](https://github.com/rumata-estor/modx3-mcp/actions/workflows/build-beta-128.yml) generates separate downloadable Actions artifacts, each with a `SHA256SUMS` and `SOURCE_COMMIT.txt`. No GitHub Release or tag is created automatically.

For the matching local Node client: `npm install -g ./modx3-mcp-1.2.8-beta.1.tgz`. Use a current client: older clients without mandatory CAS can still perform reads but will have mutations refused.

## Scope

This Beta strengthens mandatory CAS, conflict detection on TV writes, verified media overwrite via `expected_sha256`, full target fingerprints for access policies and templates, secure stops for unverifiable changes, and file metadata checks before portable static overwrites.

A MODX Manager change or external writer **can still race** after the final check. A media source that cannot provide verifiable content fails closed. A protected site should keep its independent backup strategy. Treat `MUTATION_OUTCOME_UNCERTAIN` as an unknown outcome requiring explicit review — never retry or compensate blindly. `MANUAL_REVIEW_REQUIRED` means the operation was refused before the hazardous write.

## Test sequence

1. Clone a disposable MODX 2 or MODX 3 installation; back up both database and files. Never begin on production.
2. Install the matching MODX transport through **Package Management**; when upgrading from 1.2.7, confirm the token and admin-modified settings remain unchanged.
3. Update the Node client. Start with `modx_get_site_state` and other read-only commands.
4. Make a change to a dedicated unpublished disposable object using a freshly obtained CAS snapshot. Confirm the result using an independent MCP read. Confirm stale replay is refused.
5. Change a dedicated resource TV in MODX Manager after the initial snapshot; verify conflict refusal when detected.
6. Test a disposable media file with no hash (must refuse), a freshly verified hash (may write), and a stale hash (must refuse).
7. If you test policies, use an **unassigned** test policy/template and confirm that missing or stale target fingerprints fail. Do not grant production privileges for a test.
8. For every unexpected error, stop. Read the actual state before seeking approval to retry or recover.

ACL-index migration may require deliberate administrator action on older installations; the Beta does not silently apply schema migration. See the project migration helper and the deployment guidance. For rollback, restore validated site and DB backups instead of installing old archives over the candidate.

### Component read security gate (FINDING-006)

On a disposable installation, first read a regular component source file with `modx_read_component_file`, then attempt to read a symlink pointing outside its allowed component root. The latter must refuse and never reveal external contents. Python 3 with POSIX `O_NOFOLLOW` and PHP `proc_open` is required for this secure read. Hosts with symlinked component roots should verify this deliberately stricter behavior before upgrading.

Report observations in [GitHub Issues](https://github.com/rumata-estor/modx3-mcp/issues) with MODX/PHP versions, package, source commit, action, error code and a sanitized reproduction. Do **not** publish API tokens, private keys, personal records, or unredacted logs.

**Release gate:** integration on MODX 2/3, proper 1.2.7→Beta upgrades, mandatory CAS, controlled TV/media/ACL conflict tests and accurate stop messages. Remaining TOCTOU limitations are documented and not claimed to be eliminated.
