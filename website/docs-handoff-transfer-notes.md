# Read me first — moving this to another computer

Both handoff files now live in the repo, on branch `claude/staging-remediation`:
- **website/docs-handoff-new-session-prompt.md** — the session prompt to resume the Twins staging website work.
- **website/docs-handoff-transfer-notes.md** — this file.

Nothing needs emailing. On a new computer, clone the repo and check out the branch (step 1 below); both files come with it.

## What the other computer needs before the prompt fully works

The prompt assumes the same setup as this Mac. On a different computer, these are the gaps to close first:

1. **The repo / branch — already pushed.** The work lives on branch `claude/staging-remediation`, now on GitHub at `github.com/palpulla/twins-dashboard` (latest commit `68ac2afb`). On the other computer: `git clone https://github.com/palpulla/twins-dashboard.git`, then `git checkout claude/staging-remediation`. That branch has everything. (Note: the website work sat in a git *worktree* on the original Mac, but on a fresh clone it's just a normal branch — no worktree needed.)

2. **The SSH deploy key — do NOT email this.** Deploying to staging needs the private key `~/.ssh/twins_stage_deploy_20260717`. It is a secret; never put it in an email or paste it into chat. If the other computer needs to deploy, transfer the key over a secure channel (AirDrop, a password manager, or re-add a fresh key in SiteGround's SSH Keys manager and authorize it). Until the key is present, the new session can still edit code, run tests, and plan — it just can't run `deploy:staging:release`.

3. **PHP via Docker.** `npm run check:repo` needs a `php` command. On this Mac it's a shim that runs `docker run php:8.3-cli`. The other computer needs Docker Desktop running and the same shim, or a real PHP 8.3 install.

4. **Node + npm** in the `website/twins-brand-experience` folder (`npm install` once).

5. **Browser verification** uses the claude-in-chrome extension logged into the staging site (it's behind HTTP auth / 401). You'll need to be logged in on that computer's Chrome too.

## Bottom line
On the other computer: clone the repo, check out `claude/staging-remediation`, get Docker + Node running, and (only if you want to deploy) securely bring over or re-issue the SSH key. Then open Claude Code in the repo and paste:

    Read website/docs-handoff-new-session-prompt.md and follow it to resume the Twins staging website work.
