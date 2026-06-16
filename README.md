# 🤖 PAgent: Autonomous AI Agent Framework

PAgent is a sophisticated, autonomous AI agent framework built in PHP. It is designed to act as an experienced programmer, system analyst, business analyst, or technical writer, capable of performing complex, multi-step tasks by reasoning with a Large Language Model (LLM) and utilizing a suite of integrated tools.

The agent operates on a continuous loop: it receives a task, queries the LLM with the task and gathered context, processes the LLM's response (reasoning or tool call), executes the relevant tool, and feeds the result back to the LLM, repeating the process until the task is complete.

## 🚀 Getting Started

### Prerequisites
Ensure you have PHP installed and the necessary dependencies configured.

### Installation
1. Clone this repository.
2. Install dependencies using Composer:
   ```bash
   composer install
   ```

### Configuration
The agent requires a configuration file at `config/config.json` to initialize its capabilities. This file must be properly populated with API keys and endpoints for the services it interacts with.

**Key Configuration Variables in `config/config.json`:**

| Section | Variable | Description | Required |
| :--- | :--- | :--- | :--- |
| `agent` | `timezone` | The timezone for internal timestamp generation (e.g., 'UTC'). | No (Defaults to UTC) |
| `agent` | `system-prompt` | The default instruction for the AI agent. This can be overridden by `config/system-prompt.txt`. | No |
| `gitlab` | `baseUrl` | The base URL for the GitLab instance. | Yes |
| `gitlab` | `accessToken` | The personal access token for GitLab. | Yes |
| `gitlab` | `project_id` | The ID of the GitLab project the agent operates within. | Yes |
| `llm` | `[model].baseUrl` | The base API URL for the chosen LLM (e.g., OpenAI, local endpoint). | Yes |
| `llm` | `[model].authToken` | The authentication token for the chosen LLM. | Yes |
| `llm` | `[model].model` | The specific model name to use. | Yes |
| `jira` | `apiUrl` | The base URL for the Jira instance. | Yes |
| `jira` | `apiToken` | The API token used for Jira authentication. | Yes |
| `mattermost` | `apiUrl` | The base URL for the Mattermost instance. | Yes |
| `mattermost` | `apiToken` | The authentication token for Mattermost. | Yes |
| `filesystem` | `root_directory` | The root directory for file operations. Defaults to the current working directory. | No |

### Running the Agent
The agent is executable via `run-agent.php`.

**To run the agent from the command line:**
```bash
php run-agent.php "Your complex task goes here."
```

**Web Interface Setup:**
If you wish to expose the agent through a web interface, point your web server to the `/public` directory of this repository:
```bash
cd public
php -S localhost:16666
```

## 🛠️ Agent Tools Reference

The Agent is equipped with a robust set of tools, which are dynamically discovered and used by the LLM via the `ToolCallRouterTrait`.

| Tool Name | Description | Category |
| :--- | :--- | :--- |
| `fdir` | Lists all files and subdirectories within a specified directory in the local file system. | Filesystem |
| `fread` | Reads the content of one or more specified files from the local filesystem sequentially. | Filesystem |
| `fwrite` | Writes content to a file in the local file system, completely overwriting any existing content. | Filesystem |
| `fpatch` | Modifies a range of lines of an existing file in the local file system. | Filesystem |
| `cache_save` | Saves content to a temp file and caches it for later retrieval. | Cache |
| `cache_read_latest` | Reads the most recently cached file. | Cache |
| `cache_read` | Reads a specific cached file or the latest file if no name is provided. | Cache |
| `gitlab_file` | Gets the source code for a PHP entity (class, trait, etc.) from a GitLab project. | GitLab |
| `gitlab_blame` | Gets `git blame` information for a PHP entity within a GitLab project. | GitLab |
| `gitlab_mr_diff` | Retrieves the full diff content for a specific Merge Request (MR). | GitLab |
| `gitlab_mr` | Retrieves detailed information about a specific Merge Request (MR). | GitLab |
| `gitlab_mr_comment` | Posts a detailed comment on a specific line in an MR or a general note. | GitLab |
| `gitlab_mr_note` | Posts a general, non-line-specific note/comment on a specific Merge Request (MR). | GitLab |
| `gitlab_ls` | Lists all PHP entities within a specified namespace (limited to the `\App` namespace). | GitLab |
| `jira_task` | Retrieves detailed information about a specific Jira task using its key. | Jira |
| `mm_post` | Posts a message to a specific Mattermost channel. | Mattermost |
| `mm_reply` | Posts a reply (comment) to an existing post within a Mattermost channel thread. | Mattermost |
| `mm_channel_posts` | Retrieves a paginated list of posts from a specific Mattermost channel. | Mattermost |
| `mm_thread_posts` | Retrieves all chronological replies/comments within a specific Mattermost thread. | Mattermost |
| `finish` | Stops the agent's operation and records the final result. | Control |

***

*This description and documentation were generated by the PAgent framework.*
