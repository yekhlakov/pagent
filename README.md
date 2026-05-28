# PAgent System Documentation

## 🌟 Overview

The PAgent is a sophisticated, autonomous AI agent designed to act as an experienced programmer, system analyst, business analyst, or technical writer. Its primary purpose is to take a high-level user task (`user_task`) and execute it by iteratively interacting with Large Language Models (LLMs) and external services through a structured, tool-based reasoning loop.

The agent operates by converting a complex query into a series of steps, using specialized tools (like file system access, Gitlab API calls, Jira integrations, etc.) to gather information, process data, and ultimately produce a comprehensive result.

## ⚙️ Principles of Operation

The PAgent follows a classic **ReAct (Reasoning and Acting)** loop:

1.  **Initialization**: Upon instantiation, the agent loads configuration (`config/config.json`), initializes necessary APIs (Gitlab, Jira, LLM, etc.), sets up a unique output directory, and loads the system prompt.
2.  **Tool Registration**: The `ToolCallRouterTrait` inspects all methods within the agent and its traits that are decorated with the `#[LlmTool]` attribute. This process automatically registers all available capabilities (`$toolSet`).
3.  **Execution Loop (`handle` method)**: The agent enters a continuous loop:
    *   **Query Construction**: It compiles a comprehensive query for the LLM, which includes the `system_prompt`, the original `user_task`, the running `current_context` (history of reasoning and tool calls), and any cached file lists.
    *   **LLM Interaction**: The agent sends this query to the configured LLM API (`$this->llmApi->send()`).
    *   **Response Processing**: The agent analyzes the LLM's response:
        *   If reasoning is present, it is added to the `current_context`.
        *   If tool calls are present, the agent executes them sequentially via the `ToolCallRouterTrait`.
    *   **Action/Router**: The `ToolCallRouterTrait` parses the LLM's response. If tool calls were made, the corresponding methods in the traits are executed. The results of these tool calls (e.g., file contents, API data) are appended to the `current_context`.
    *   **Termination**: The loop continues until the `ToolCallRouterTrait` determines that no further tool calls are necessary or the `Finish` tool is explicitly called, at which point the final result is returned.

## 🛠️ Required Configuration

The agent relies heavily on a configuration file, typically `config/config.json`. This file must define the necessary credentials and parameters for all external services.

Key configuration sections include:

*   **`agent`**: Defines core agent settings, such as:
    *   `timezone`: The timezone used for file naming and operations (e.g., "UTC").
    *   `system-prompt-file`: Path to a custom system prompt file, or `system-prompt` for an inline prompt.
*   **`llm`**: Defines the configuration for the chosen LLM provider (e.g., 'openai', 'local').
    *   `baseUrl`: The base URL of the LLM service.
    *   `authToken`: The API key or authentication token.
    *   `model`: The specific model name to use.
*   **`gitlab`**: Configuration for interacting with GitLab.
    *   `baseUrl`: The base URL of the GitLab instance.
    *   `accessToken`: The personal access token.
    *   `project_id`: The specific project ID the agent should target.
*   **`jira`**: Configuration for interacting with Jira.
    *   `apiUrl`: The base URL of the Jira instance.
    *   `apiToken`: The API token for authentication.
    *   `customFieldMap`: Optional mapping for custom Jira fields.
*   **`mattermost`**: Configuration for interacting with Mattermost.
    *   `apiUrl`: The base URL of the Mattermost instance.
    *   `apiToken`: The API token for authentication.
*   **`filesystem`**: Configuration for local file operations.
    *   `root_directory`: The root directory from which all file paths are resolved.

## 🤖 Agent Capabilities (Tools)

The PAgent utilizes a set of specialized tools, exposed via the `#[LlmTool]` attribute, allowing the LLM to instruct the agent to perform specific actions. These tools are automatically discovered and listed by the agent.

| Tool Name | Description | Functionality |
| :--- | :--- | :--- |
| `fdir` | Lists all files and subdirectories within a specified directory in the local file system. Don`t query directory names like `.` and `..`! | File System Exploration |
| `fread` | Reads the content of one or more specified files from the local filesystem sequentially. | File System Reading |
| `fwrite` | Writes content to a file in the local file system, completely overwriting any existing content. If the file does not exist, it will be created. | File System Writing |
| `fpatch` | Modifies a segment of an existing file in the local file system. The replacement starts at startLine and ends at endLine. If content is empty, the segment is deleted. | File Modification (Patching) |
| `cache_save` | If you need to save something to a temp file, call this function. The file is put to a cache and can be retrieved later. | File Caching (Write) |
| `cache_read_latest` | If you need to read your most recently cached file, call this tool. | File Caching (Read Latest) |
| `cache_read` | If you need to read a file you have previously written to file cache, call this function. | File Caching (Read Specific) |
| `finish` | If you have nothing more to do, call this function to stop the operation. | Task Termination |
| `gitlab_file` | If you need source code for a php entity (class, attribute, interface, trait) in a Gitlab project, use this function to get it by its fully qualified name. | Gitlab Code Retrieval |
| `gitlab_blame` | If you need `git blame` info for a php entity in the Gitlab project, use this function to get it. | Gitlab Code History |
| `gitlab_ls` | If you need a list of all php entities in a namespace in Gitlab project, use this function. | Gitlab Namespace Listing |
| `jira_task` | If you need to get a Jira task text, use this tool. The task text (along with several additional fields and comments) will be added to your context. | Jira Issue Retrieval |
| `mm_post` | Posts a message to a specific Mattermost channel. | Communication/Notification |

---
*Generated by PAgent.*
