# AGENTS.md — yii2-saml
<!-- HB-KB:BEGIN operating-card kb=f89c7c6bbfe5142634f48e20c1375cf01074a287 -->
## Handbid operating card (generated from hb-kb; do not edit here)

Every Handbid agent and human follows this card. It is stamped from hb-kb by `hbkb-adapters sync` and checked in CI; change it only by an hb-kb PR.

1. **Authority.** `source_of_truth` hb-kb pages, then this repo's `AGENTS.md` facts, then the spec and Linear card, then the lane handoff. Memory and chats never outrank them. Conflicting authorities: stop and report. See [AGENTS.md](../hb-kb/AGENTS.md).
2. **Start.** Run `bash ../hb-kb/cli/tools/agent-preflight.sh` (it pulls a clean KB `main` and lists SOP changes since this machine last looked: tell the human), then `../hb-kb/cli/tools/hb-kb-route --repo <repo> --paths <changed paths> --stage <card state>`, and read every doc it lists.
3. **Read the SOP from the file** at each phase boundary (kickoff, build, mutations, role change, exit) and list `compliant / deviation + mitigation` before acting. Memory of an SOP is not the SOP. See [agent-guidelines](../hb-kb/engineering/ai-agents/agent-guidelines.md).
4. **Cadence.** Non-trivial work: plan table, approval, then present → approve → execute → review one step at a time. Autonomous pipeline stages (blind author, headless reviewers, routines) run under their stage contract. Don't widen scope: stop and say so. See [agent-guidelines](../hb-kb/engineering/ai-agents/agent-guidelines.md).
5. **Lanes.** One cake = one `hb-env` lane slug = one branch, worktree set, env and `hb-envs/<slug>/handoff.md`. Never switch a canonical checkout's branch. Keep the handoff current. See [lane-handoff-standard](../hb-kb/engineering/ai-agents/lane-handoff-standard.md).
6. **Routing.** Code the deployed app runs, and migrations, go in `yii2-handbid-ext`. Wrapper tooling, Docker, env templates and docs go in `yii2-handbid`. Never edit `vendor/`. Cite test results with the commit pin, from your own lane. See [agent-worktrees-and-repo-routing](../hb-kb/engineering/standards/agent-worktrees-and-repo-routing.md).
7. **Pipeline.** Spec Drafting → Independent Spec Review → hardening kickoff → In Dev → Independent Audit → PR review → Ready to Merge → Ready for QA; the full state list and every exit are in [stages-and-gates](../hb-kb/engineering/sdlc/stages-and-gates.md). Read its § Human gates before any card state change.
8. **Hardening.** Every behaviour-changing card gets a blind pass: the kickoff and author launch come before In Dev. Mergeable only at READY: state ≥ Ready to Merge AND `Hardening-Passed` AND `Mutations-Passed`, and hosted review `CLEAR` at the head SHA. See [hardening-phase](../hb-kb/engineering/sdlc/hardening-phase.md).
9. **Rulings.** Nobody adjudicates their own findings except through a recorded routine ruling (complete evidence, no protected path) or a recorded product-owner override. Rulings are recorded before Ready to Merge. See [hardening-phase](../hb-kb/engineering/sdlc/hardening-phase.md).
10. **Review sub-agents** get raw artifacts (logs, code, ledgers), never your reasoning. Verify every claim they return. An absence needs a witness: "did not run" and "did not work" look the same in a log. See [known-agent-mistakes](../hb-kb/engineering/ai-agents/known-agent-mistakes.md).
11. **Safety.** No secrets or PII anywhere; credentials only via the 1Password tooling. Production changes, release merges, deploys and destructive operations need the documented human approval. Linear through the API tooling, never the MCP connector. See [security-standards](../hb-kb/engineering/security/security-standards.md).
12. **Record decisions** in the spec or KB in the same session, tagged `AS-BUILT (YYYY-MM-DD)`. Every artifact (code, Linear, specs, commits) is in English. See [spec-currency](../hb-kb/engineering/sdlc/spec-currency.md).
13. **Start work.** A card-shaped request ('fix / work on / pick up / continue HAN-NNNN', or a bare card id) runs start-work first: read the card's state, act only as that stage allows, stop at its gate. See [start-work](../hb-kb/engineering/ai-agents/start-work.md).
<!-- HB-KB:END operating-card -->

**ARCHIVED — do not work here** unless the product owner names this repo.

## What this repo is

Handbid's fork of `asasmoyo/yii2-saml`, a Yii 2 extension for SAML single sign-on (wraps `onelogin/php-saml`), used for Disney,
Comcast and other SSO clients. Local checkout is `handbid-yii2-saml`. Production loads it as `asasmoyo/yii2-saml` from
`yii2-handbid-app/composer.json`, pinned to branch `dev-handbid-php8`, which carries the Handbid customizations and is not `master`
(the default, which tracks upstream). Source in `src/` (`Saml.php`, `actions/`, `config/`); phpunit in `tests/`.

## Branches

- Default: `master`. The deployed line is `handbid-php8`.

## Related repos

- `yii2-handbid` — pins this package; SAML configuration lives in `yii2-handbid-ext`.
