# PAgent — Agent instructions

## Commands

| Action | Command |
|--------|---------|
| Format code | `vendor/bin/pint` (excludes `public/`, `output/`, `vendor/`) |
| Run agent (CLI) | `php run-agent.php` |
| Run worker | `php worker.php <job_dir>` |
| Serve web UI | Point web server to `public/` |

No tests exist. No CI. No typechecker.

## Architecture

```
public/          Web UI entrypoint
src/Agent.php    Core agent loop (LLM query → tool execution → repeat)
src/Traits/Tools/*.php   Tools as traits, each annotated with #[LlmTool]
src/Api/         HTTP client wrappers (Bitbucket, Gitlab, Jira, Mattermost, LLM)
worker.php       CLI worker spawned by web UI for background job execution
run-agent.php    Example standalone script
config/config.json  Required config (gitignored); see config.example.json
```

- Tools auto-discovered via `#[LlmTool]` attribute on public methods.
- Tools are grouped by **tag** (snake_case of trait name): `filesystem`, `gitlab`, `bitbucket`, `jira`, `mattermost`, `cache`, `finish`.
- `withTools(...)` accepts tool names **or** tag names to enable groups.
- Loop halts when LLM returns no tool calls OR a tool method returns `false`.
- Output dir: `output/<timestamp>-<id>-<llm>/`. Session file cache persisted via `file-cache.json`.

## Config

`config/config.json` sections: `agent` (timezone, system-prompt), `llm.*` (baseUrl, authToken, model), `bitbucket`, `gitlab`, `jira`, `mattermost`, `filesystem` (root_directory).

## Known bugs

- `src/Agent.php:101`: uses undefined `$isFile` — should be `$systemPromptFile`.
- `src/Agent.php:121`: uses `$llm` instead of `$this->llm`.
- `src/Traits/Tools/Bitbucket.php:37`: calls `$this->getVcsFile($this->gitlabApi, ...)` — uses Gitlab API for Bitbucket tool (likely wrong).

## Miscellaneous

- LLM seed is hardcoded to `666` (`src/Api/LlmApi.php:84`).
- On Windows, `proc_open` does not truly detach; web UI comment suggests `start /B`.
- Dependency: `php-code-archeology/php-code-archeology` ^2.11.
