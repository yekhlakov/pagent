# PAgent Framework

PAgent is a sophisticated, modular, AI-driven agent framework designed to automate complex tasks by orchestrating interactions between Large Language Models (LLMs) and various external services (GitLab, Jira, Mattermost, local filesystem).

## 🚀 Purpose

The primary purpose of PAgent is to act as an intelligent, autonomous worker. Given a high-level task or query, the Agent uses its internal reasoning loop and a suite of specialized tools to gather information, process data, interact with external APIs, and deliver a final result. It functions as a self-correcting loop: LLM suggests an action -> Agent executes action via tool -> Agent feeds result back to LLM -> Repeat until task completion.

## ⚙️ Principles of Operation

### 1. Initialization and Configuration
The Agent is instantiated using a configuration file (`config/config.json`) and an LLM identifier. During construction, it initializes:
*   **Timezone**: Sets the operating timezone based on configuration.
*   **Output Structure**: Creates a unique, timestamped output directory (`output/<timestamp>-<id>-<llm>/`) to store all generated artifacts.
*   **API Clients**: Initializes specific clients for GitLab, Jira, LLM provider, and Mattermost using credentials provided in the configuration.
*   **System Prompt**: Loads the agent's core instructions, which define its persona and operational constraints.

### 2. The Execution Loop (`handle` method)
The core logic operates in a continuous loop:
1.  **Context Assembly**: The Agent gathers all relevant information: the initial user task, the current operational context (reasoning history), and any previously cached files.
2.  **LLM Query**: This assembled context is sent to the configured LLM API.
3.  **Response Processing**: The Agent receives the LLM's response.
    *   If the LLM provides a `reasoning_content`, this is appended to the internal context, improving the agent's memory.
    *   The Agent attempts to parse the response for `tool_calls`.
4.  **Tool Execution (Routing)**: If tool calls are present, the `ToolCallRouterTrait` identifies the required function and executes the corresponding method within the Agent. The result of the tool execution is then added back to the context for the next LLM query.
5.  **Termination**: The loop breaks when the LLM response contains no tool calls, indicating the task is complete, or if a critical error occurs.

### 3. Tooling and Modularity
The framework is highly modular, relying on traits to encapsulate specific functionalities:
*   **`Filesystem`**: Handles low-level file operations (`fdir`, `fread`, `fwrite`, `fpatch`) and path resolution.
*   **`Cache`**: Manages persistence by saving and retrieving file content from a cache, allowing the agent to resume tasks.
*   **`Gitlab`**: Provides specialized access to Gitlab repositories, allowing the agent to fetch source code and perform `git blame`.
*   **`Jira`**: Integrates with Jira to retrieve detailed information about specific tasks.
*   **`Mattermost`**: Allows the agent to post messages and retrieve post history from Mattermost channels and threads.
*   **`CurlTrait`**: Provides a reusable HTTP client for low-level API communication.

## 🛠️ Required Configuration Variables

The Agent requires a `config/config.json` file (or a specified path) containing the following structural information:

| Section | Variable | Description | Required |
| :--- | :--- | :--- | :--- |
| `agent` | `timezone` | The IANA timezone string (e.g., `America/New_York`) used for date/time operations. | Yes |
| `agent` | `system-prompt` | The default instruction set for the AI. Can be overridden by `system-prompt-file`. | Recommended |
| `gitlab` | `baseUrl` | The base URL of the GitLab instance. | Yes |
| `gitlab` | `accessToken` | The personal access token for GitLab API interaction. | Yes |
| `gitlab` | `project_id` | The ID of the GitLab project the agent operates on. | Yes |
| `llm` | *Provider Config* | Configuration block for the selected LLM (e.g., `local`). Must contain `baseUrl`, `authToken`, and `model` name. | Yes |
| `jira` | `apiUrl` | The base URL for the Jira instance. | Yes |
| `jira` | `apiToken` | The API token used for Jira authentication. | Yes |
| `mattermost` | `apiUrl` | The base URL for the Mattermost instance. | Yes |
| `mattermost` | `apiToken` | The API token used for Mattermost authentication. | Yes |
| `filesystem` | `root_directory` | The absolute path to the project's root directory (defaults to current working directory). | Recommended |

## 🤖 Available Tools

The Agent exposes a set of functional tools, defined by the `LlmTool` attribute, allowing the LLM to delegate tasks to the underlying PHP logic.

| Tool Name | Description |
| :--- | :--- |
| `fdir` | Lists all files and subdirectories within a specified directory in the local file system. Don't query directory names like `.` and `..`! |
| `fread` | Reads the content of one or more specified files from the local filesystem sequentially. |
| `fwrite` | Writes content to a file in the local file system, completely overwriting any existing content. If the file does not exist, it will be created. |
| `fpatch` | Modifies a range of lines of an existing file in the local file system. The replacement starts after the `startLine` and ends just before `endLine`. If `content` is empty, the segment is deleted. Lines are numbered starting from 1. |
| `cache_save` | If you need to save something to a temp file, call this function. The file is put to a cache and can be retrieved later. |
| `cache_read_latest` | If you need to read your most recently cached file, call this tool. |
| `cache_read` | If you need to read a file you have previously written to file cache, call this function. |
| `finish` | If you have finished your task, call this function to stop the operation. |
| `gitlab_file` | If you need source code for a php entity (class, attribute, interface, trait) in a Gitlab project, use this function to get it by its fully qualified name. The content of the file containing this entity (and associated test, if there's one) will be appended to your current context. Only entities under the \App namespace will be returned. Always check that className contains namespace. |
| `gitlab_blame` | If you need `git blame` info for a php entity (class, attribute, interface, trait) in the Gitlab project, use this function to get it. The blame content for the entity will be appended to your current context. Only entities under the \App namespace will be analyzed. Always check that className contains namespace. |
| `gitlab_ls` | If you need to list of all php entities in a namespace in Gitlab project, use this function. The list will be appended to your context. Only namespaces under \App will be available. |
| `jira_task` | If you need to get a Jira task text, use this tool. The task text (along with several additional fields and comments) will be added to your context. |
| `mm_post` | Posts a message to a specific Mattermost channel. |
| `mm_reply` | Posts a reply (comment) to an existing post within a Mattermost channel thread. |
| `mm_channel_posts` | Retrieves a paginated list of posts from a specific Mattermost channel. |
| `mm_thread_posts` | Retrieves all chronological replies/comments within a specific Mattermost thread. |

***

*This description was generated by the AI Agent.*
