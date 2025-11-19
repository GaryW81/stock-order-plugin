PROJECT-INSTRUCTIONS.md

Stock Order Plugin – Master Project Instructions (High-Speed Workflow Edition)

This document defines the rules and workflow for building the Stock Order Plugin using ChatGPT, GitHub, Codex, and VS Code.
It is designed for maximum development speed with strict validation to prevent avoidable errors.

PROJECT PURPOSE

Build a custom WordPress + WooCommerce ERP-style plugin for forecasting, supplier ordering, CBM/container planning, and goods-in.
The final product is a complete standalone plugin stored in GitHub.

OVERALL WORKFLOW (FASTEST METHOD)

GitHub is the single source of truth for all plugin files.

VS Code is the local working copy of the plugin.

Codex performs direct file edits, multi-file refactors, and reviews.

This ChatGPT project thread acts as:

planning

architecture

debugging

code reviews

generating new modules

Canvas is optional and only for drafts or layouts.

Code Snippets is only used for temporary test tools.

PHASE WORKFLOW

Each phase produces real plugin files and commits them to GitHub.

Process:

User uploads existing plugin files (only if context is needed)

ChatGPT produces a plan for the phase

User approves

Codex generates or modifies the required files

User commits files to GitHub

ChatGPT reviews logic, confirms stability, and moves to the next phase

Phases must extend previous phases without breaking compatibility unless authorised.

GITHUB + CODEX DEVELOPMENT RULES

Codex may edit files directly inside the plugin structure.

All file paths must match the plugin folder structure exactly.

Every change must be output as a complete file (never partial patches).

All code must be safe to paste into the file with no manual cleanup.

Codex must not assume a file exists unless created in a previous phase.

Multi-file edits must be grouped into a single, clear plan before execution.

PLUGIN FILE & FOLDER STRUCTURE

All work must follow this layout:

stock-order-plugin/
│
├── stock-order-plugin.php
├── /includes
├── /admin
└── /assets   (used only if needed)


Each new feature must be implemented as a full file within this structure.
No loose functions. No partial modules.

VERSIONING RULES

Each PHP file must include: Phase + Description + File Version

File version increases every time the file is changed

ChatGPT must state: “V7.3 → V7.4” when updating

The main plugin file uses semantic versioning: 0.x.x

Git tags represent milestone plugin versions

APPROVAL FORMAT (A/B/C/D)

Before generating or modifying files, ChatGPT must provide:

A) What is being changed or added
B) Why it is needed
C) Which files are affected
D) Risks or side effects

User must approve before Codex runs code modifications.

PHASE STRUCTURE (CHAT FORMAT)

Every phase must include:

Short summary

Key assumptions

A/B/C/D plan

Exact list of files Codex will modify

Brief testing notes

Integration notes

CODING STANDARDS

Use WordPress/WooCommerce APIs and standard PHP only

No external APIs

ALWAYS output full updated files

All functions, classes, hooks, DB tables, and meta keys must use prefix sop_

Public functions and classes require docblocks

Avoid duplicate function names

Keep inline comments minimal but purposeful

DATABASE RULES

When creating or modifying tables, options, or meta keys:

Explain what the data stores

Explain why it is needed

Explain how it is initialised

Ensure forward compatibility

Use only sop_ prefixes

Never overwrite important meta

DB migrations must be safe and idempotent

SNIPPET RULES

Snippets are temporary admin-only tools

If plugin logic replaces a snippet, ChatGPT must specify which snippet to disable

Snippets must be removed once the logic is inside the plugin

FAST CODING BEHAVIOUR

ChatGPT must keep the project moving with minimal back-and-forth

No unnecessary clarifications when the intent is clear

All loose ends are wrapped up at the end of each phase

When the user asks for an amendment, ChatGPT must output the corrected full file immediately

ADMIN UI & SYSTEM ARCHITECTURE

Use WordPress admin menus and WooCommerce admin hooks

Focus admin screens on forecasting, suppliers, pre-order sheet, and goods-in

Avoid front-end code unless explicitly required

Admin screens must scale smoothly for large product catalogs

FINAL PLUGIN DELIVERY REQUIREMENTS

At project completion, ChatGPT must deliver:

Main plugin file

Full /includes folder

Full /admin folder

All helper classes

Full plugin directory ready to zip

Fully installable plugin package

FORECASTING RULES

All forecasting features must respect:

Global stock buffer

Supplier-specific buffer

MOQ

Supplier lead time

Historical sales data

VALIDATION RULE – “CHECK IT TWICE, DO IT ONCE”

To prevent basic, time-wasting errors (missing PHP tags, unclosed braces, truncated functions), every phase must include a validation pass before completion.

Validation Requirements

ChatGPT must generate complete files only.

After generating each file, ChatGPT must internally re-check for:

Missing <?php

Missing/mismatched {}

Premature file termination

Duplicate function names within the phase

At the end of each phase, ChatGPT must output ready-to-run lint commands, e.g.:

php -l stock-order-plugin/includes/db-helpers.php
php -l stock-order-plugin/admin/preorder-ui.php


The user runs lint checks.
No plugin activation until every file returns:
“No syntax errors detected.”

If there is ANY lint error, ChatGPT must immediately regenerate corrected full file(s).

This ensures correctness before activation — preventing 90% of avoidable debugging.

END OF MASTER PROJECT INSTRUCTIONS