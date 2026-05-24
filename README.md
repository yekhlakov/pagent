# Agent System Documentation

## 🚀 Overview
This document describes the architecture and operation of the `Agent` class, an autonomous AI system designed to execute complex, multi-step tasks based on a single initial user query. It functions as a closed-loop reasoning engine, leveraging external tools and a Large Language Model (LLM) to achieve its goals.

## ⚙️ Principles of Operation (The Agent Loop)

The agent operates via a continuous, iterative process defined in the `handle(string $query)` method:

1.  **Initialization:** The agent begins by setting up a unique, timestamped output directory and loading all necessary configurations, including the system prompt and API credentials.
2.  **Context Building:** In each cycle, the agent constructs a comprehensive query package by combining:
    *   The static **System Prompt** (defining its role and constraints).
    *   The **User Task** (the original goal provided by the user).
    *   The **Current Context** (a running log of all previous reasoning, actions, and tool calls).
    *   Any **Saved Files** (retrieved from the file cache).
3.  **LLM Query:** This entire context package is sent to the configured LLM API (`$this->llmApi->send(...)`).
4.  **Response Processing & Reasoning:** The agent parses the LLM response:
    *   It extracts the **Reasoning Content**, appending it to the `current_context`.
    *   It identifies any **Tool Calls** requested by the LLM.
    *   It logs the details of these tool calls in the `current_context`.
5.  **Action Execution/Routing:** The agent uses a router mechanism (`$this->parseLlmResponse()`) to determine the next step—whether to execute a tool, generate a final answer, or continue the loop.
6.  **Termination:** The loop terminates when the router signals that no further action is required, indicating task completion.

## 🔑 Required Configuration Variables

The agent is highly dependent on a configuration file, typically `config/config.json`. This file must define parameters for the Agent's behavior, the LLM provider, and the external services it interacts with (GitLab and Jira).

### Configuration Structure:

**1. Agent Configuration (`agent` section):**
*   `timezone`: The timezone used for internal timestamping (e.g., `'UTC'`).
*   `system-prompt`: The default text prompt defining the agent's persona and rules.
*   `system-prompt-file`: (Optional) Path to an external file that overrides the default system prompt.

**2. LLM Configuration (`llm` section):**
*   This section is provider-specific and must be structured to allow dynamic switching of LLM services.
*   For a chosen LLM provider, the following parameters are required:
    *   `baseUrl`: The API endpoint URL.
    *   `authToken`: The necessary authentication token/key.
    *   `model`: The specific model identifier (e.g., `'gpt-4'`).

**3. GitLab Configuration (`gitlab` section):**
*   `baseUrl`: The base URL for the GitLab instance.
*   `accessToken`: The API access token for GitLab authentication.
*   `project_id`: The ID of the specific GitLab project the agent is scoped to.

**4. Jira Configuration (`jira` section):**
*   `apiUrl`: The base URL for the Jira instance.
*   `apiToken`: The API token required for Jira authentication.
*   `customFieldMap`: (Optional) A mapping array used to handle specific Jira custom fields.


*This README was written by this agent itself, see the file `run-agent.php` included in this repo*
