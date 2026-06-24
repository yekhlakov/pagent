# PAgent: Autonomous AI Software Development Agent

## 🚀 Overview

PAgent is a sophisticated, autonomous AI Agent designed to function as an experienced programmer, system analyst, business analyst, and technical writer. It is capable of executing complex, multi-step tasks by intelligently interacting with external services (such as Git repositories, issue trackers, and communication platforms) and local file systems, all orchestrated by a Large Language Model (LLM).

The agent operates in a continuous loop: it queries the LLM, receives instructions (which may include tool calls), executes the necessary tools, and feeds the results back to the LLM until the task is successfully completed.

## ⚙️ Principles of Operation

### Initialization
Upon execution, the agent initializes several key components:
1.  **Configuration Loading:** It reads a centralized configuration file (`config/config.json`) to set up API keys, base URLs, and agent parameters.
2.  **Session Setup:** It creates a unique, timestamped output directory for the current session, ensuring that all generated files and logs are isolated.
3.  **Context Building:** It establishes a comprehensive conversation context, starting with a system prompt and the initial user task.

### Execution Loop
The core logic resides in the `Agent` class and follows these steps:
1.  **LLM Query:** The agent sends the current context and its full registry of available tools to the configured LLM API.
2.  **Tool Call Parsing:** The agent parses the LLM's response. If the LLM requests an action, it returns a list of tool calls.
3.  **Tool Execution:** The agent identifies the requested tool and executes the corresponding PHP method. The execution result (success, output, or error) is captured.
4.  **Context Update:** The result of the tool execution is immediately appended to the context as a `tool` role message.
5.  **Iteration:** The loop repeats until the LLM responds without requesting any further tool calls, signaling task completion.

### Robustness Features
*   **Path Resolution:** The `Filesystem` trait provides robust path handling, normalizing paths and resolving `..` components safely against a configured root directory.
*   **File Caching:** The `Cache` trait allows the agent to store and retrieve file content, preventing redundant API calls or repeated file operations during a single session.
*   **Tool Discovery:** The `ToolCallRouterTrait` automatically discovers all available functions (tools) by inspecting methods annotated with the `LlmTool` attribute across all active traits.

## 🛠️ Required Configuration

The agent requires a comprehensive configuration file, typically located at `config/config.json`. This file must define parameters for the agent itself and all integrated services.

### Agent Configuration
*   `agent.timezone`: The agent's internal timezone (e.g., `'UTC'`).
*   `agent.system-prompt`: A custom default instruction for the AI.
*   `agent.system-prompt-file`: Optional path to a custom system prompt file.

### LLM Configuration
*   `llm.[llm_name].baseUrl`: The base URL for the chosen LLM provider.
*   `llm.[llm_name].authToken`: The authentication token for the LLM provider.
*   `llm.[llm_name].model`: The specific model identifier (e.g., `'gpt-4o'`).

### API Service Configuration
The following services require their respective API keys and base URLs:
*   **Bitbucket:** `bitbucket.baseUrl`, `bitbucket.accessToken`.
*   **GitLab:** `gitlab.baseUrl`, `gitlab.accessToken`, `gitlab.project_id`.
*   **Jira:** `jira.apiUrl`, `jira.apiToken`, `jira.customFieldMap`.
*   **Mattermost:** `mattermost.apiUrl`, `mattermost.apiToken`.
*   **Filesystem:** `filesystem.root_directory` (The absolute root path for all local operations).

## 🔧 Available Tools

The agent is equipped with a suite of tools to interact with its environment and external services. These tools are automatically discovered and presented to the LLM.

| Tool Name | Description |
| :--- | :--- |
| `fdir` | Lists all files and subdirectories within a specified directory in the local file system. Don't query directory names like `.` and `..`! |
| `fread` | Reads the content of one or more specified files from the local filesystem sequentially. |
| `fwrite` | Writes content to a file in the local file system, completely overwriting any existing content. If the file does not exist, it will be created. |
| `finish` | If you have finished your task, call this function to stop the operation |
| `bitbucket_file` | Retrieves source code for php files (*.php) or php entities (class, attribute, interface, trait) from the Bitbucket project. The content of the files (and associated test, if there's one) will be appended to your current context. Only entities under the \App namespace will be returned. |
| `cache_save` | If you need to save something to a temp file, call this function. The file is put to a cache and can be retrieved later. |
| `cache_read_latest` | If you need to read your most recently cached file, call this tool |
| `cache_read` | If you need to read a file you have previously written to file cache, call this function |
| `gitlab_file` | Retrieves source code for php files (*.php) or php entities (class, attribute, interface, trait) from a Gitlab project. The content of the files (and associated test, if there's one) will be appended to your current context. Only entities under the \App namespace will be returned. |
| `gitlab_blame` | If you need `git blame` info for a php entity (class, attribute, interface, trait) in the Gitlab project, use this function to get it. The blame content for the entity will be appended to your current context. Only entities under the \App namespace will be analyzed. Always check that className contains namespace. |
| `gitlab_mr_diff` | Retrieves the full diff content for a specific Merge Request (MR) in a Gitlab project. The diff content will be appended to your current context. |
| `gitlab_mr` | Retrieves detailed information about a specific Merge Request (MR) in a Gitlab project. The MR details will be appended to your current context. |
| `gitlab_mr_comment` | Posts a detailed comment on a specific line in a Merge Request (MR) in a Gitlab project. If line-specific details (baseSha, startSha, headSha, newPath, newLine) are not provided, it falls back to posting a general note. |
| `gitlab_mr_note` | Posts a general, non-line-specific note/comment on a specific Merge Request (MR) in a Gitlab project. The outcome (success or failure) is added to the context. |
| `gitlab_ls` | If you need to list of all php entities in a namespace in Gitlab project, use this function. The list will be appended to your context. Only namespaces under \App will be available. |
| `jira_task` | If you need to get a Jira task text, use this tool. The task text (along with several additional fields and comments) will be added to your context. |
| `mm_post` | Posts a message to a specific Mattermost channel. |
| `mm_reply` | Posts a reply (comment) to an existing post within a Mattermost channel thread. |
| `mm_channel_posts` | Retrieves a paginated list of posts from a specific Mattermost channel. |
| `mm_thread_posts` | Retrieves all chronological replies/comments within a specific Mattermost thread. |

## 🌐 Web Interface & Getting Started

The agent is designed to be run through a web interface.

### How to Start
To deploy and start the agent, simply point your web server to the **`/public`** directory of this repository, following standard PHP deployment procedures.

## 🤖 Generated by an Agent

*This description was automatically generated by an AI agent.*
