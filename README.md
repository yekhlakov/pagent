# 🤖 PAgent (PHP AI Agent)

This document describes the architecture, operational principles, and available tooling for the PAgent system.

## 🧠 Purpose and Overview

PAgent is an autonomous AI agent designed to act as a highly capable programmer, system analyst, business analyst, or technical writer. Its primary purpose is to accept a high-level task or query from a user and execute the necessary steps—including reading code, interacting with external services (GitLab, Jira, Mattermost), and performing file operations—to achieve the desired outcome.

The agent operates as a closed-loop reasoning system, leveraging a Large Language Model (LLM) as its core decision-maker.

## ⚙️ Principles of Operation

The agent operates using a sophisticated iterative loop driven by the LLM:

1. **Initialization**: The `Agent` class initializes itself by loading configuration from `config/config.json`. It sets up connections to various external APIs (GitLab, Jira, Mattermost, LLM service) and creates a unique, time-stamped output directory for its session.
2. **Context Building**: The agent maintains a persistent `current_context` which accumulates all relevant information: the initial user task, the system prompt, retrieved files, and the agent's own reasoning steps.
3. **Querying the LLM**: In each iteration, the agent sends the aggregated context and the list of available tools to the LLM.
4. **Decision Making**: The LLM processes the query and determines the next action. It can either:
    * **Generate a final response**: If the LLM determines the task is complete, it returns content, and the agent terminates.
    * **Request a Tool Call**: If the LLM needs external data (e.g., reading a file, getting a Jira ticket, listing a directory), it returns a structured request for a specific tool.
5. **Execution**: The `ToolCallRouterTrait` intercepts the tool request, identifies the corresponding PHP method, and executes it. The method runs the necessary logic (e.g., calling `fdir`, using `GitlabApi`).
6. **Feedback Loop**: The output of the executed tool is appended back to the `current_context`. This new information is then fed back into the LLM in the next iteration, allowing the agent to reason over the retrieved data and decide the subsequent step until the task is complete.

## 🛠️ Required Configuration Variables

The agent relies heavily on the `config/config.json` file for its setup. Key variables required for full functionality include:

| Configuration Section | Required Variables | Purpose |
| :--- | :--- | :--- |
| **`agent`** | `timezone` | Sets the timezone for file naming and operations (default: `UTC`). |
| | `system-prompt` / `system-prompt-file` | Defines the core persona and rules for the AI agent. |
| **`llm`** | (e.g., `local` config) `baseUrl` | The endpoint URL for the Large Language Model service. |
| | `authToken` | Authentication token for the LLM service. |
| | `model` | The specific LLM model identifier to use. |
| **`gitlab`** | `baseUrl` | The base URL of the GitLab instance. |
| | `accessToken` | API token required for Gitlab interactions. |
| | `project_id` | The ID of the specific project the agent will interact with. |
| **`jira`** | `apiUrl` | The API URL for the Jira instance. |
| | `apiToken` | Authentication token for Jira. |
| | `customFieldMap` | Mapping for custom fields in Jira issues (optional). |
| **`mattermost`** | `apiUrl` | The base URL of the Mattermost instance. |
| | `apiToken` | Authentication token for Mattermost. |
| **`filesystem`** | `root_directory` | The absolute path to the project's root directory (defaults to current working directory). |

## 🧩 Available Tools

The agent exposes a suite of specialized tools, managed via the `LlmTool` attribute, allowing the LLM to programmatically interact with the environment and external services.

| Tool Name | Description | Source Trait |
| :--- | :--- | :--- |
| `fdir` | Lists all files and subdirectories within a specified directory in the local file system. Don't query directory names like `.` and `..`! | `Filesystem` |
| `fread` | Reads the content of one or more specified files from the local filesystem sequentially. | `Filesystem` |
| `fwrite` | Writes content to a file in the local file system, completely overwriting any existing content. If the file does not exist, it will be created. | `Filesystem` |
| `fpatch` | Modifies a range of lines of an existing file in the local file system. The replacement starts after the `startLine` and ends just before `endLine`. If `content` is empty, the segment is deleted. Lines are numbered starting from 1. | `Filesystem` |
| `cache_save` | If you need to save something to a temp file, call this function. The file is put to a cache and can be retrieved later. | `Cache` |
| `cache_read_latest` | If you need to read your most recently cached file, call this tool | `Cache` |
| `cache_read` | If you need to read a file you have previously written to file cache, call this function | `Cache` |
| `finish` | If you have finished your task, call this function to stop the operation | `Finish` |
| `gitlab_file` | If you need source code for a php entity (class, attribute, interface, trait) in a Gitlab project, use this function to get it by its fully qualified name. | `Gitlab` |
| `gitlab_blame` | If you need `git blame` info for a php entity (class, attribute, interface, trait) in the Gitlab project, use this function to get it. | `Gitlab` |
| `gitlab_ls` | If you need to list of all php entities in a namespace in Gitlab project, use this function. | `Gitlab` |
| `jira_task` | If you need to get a Jira task text, use this tool. The task text (along with several additional fields and comments) will be added to your context. | `Jira` |
| `mm_post` | Posts a message to a specific Mattermost channel. | `Mattermost` |
| `mm_reply` | Posts a reply (comment) to an existing post within a Mattermost channel thread. | `Mattermost` |
| `mm_channel_posts` | Retrieves a paginated list of posts from a specific Mattermost channel. | `Mattermost` |
| `mm_thread_posts` | Retrieves all chronological replies/comments within a specific Mattermost thread. | `Mattermost` |

***

*This description was generated by the PAgent AI Agent.*