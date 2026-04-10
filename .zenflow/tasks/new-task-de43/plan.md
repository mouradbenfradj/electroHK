# Auto

## Configuration
- **Artifacts Path**: {@artifacts_path} → `.zenflow/tasks/{task_id}`

---

## Agent Instructions

Ask the user questions when anything is unclear or needs their input. This includes:
- Ambiguous or incomplete requirements
- Technical decisions that affect architecture or user experience
- Trade-offs that require business context

Do not make assumptions on important decisions — get clarification first.

---

## Workflow Steps

### [ ] Step: Implementation
<!-- chat-id: 7695b096-46da-44da-9aa5-c7034aba854a -->

**Debug requests, questions, and investigations:** answer or investigate first. Do not create a plan upfront — the user needs an answer, not a plan. A plan may become relevant later once the investigation reveals what needs to change.

**For all other tasks**, before writing any code, assess the scope of the actual change (not the prompt length — a one-sentence prompt can describe a large feature). Scale your approach:

- **Trivial** (typo, config tweak, single obvious change): implement directly, no plan needed.
- **Small** (a few files, clear what to do): write 2–3 sentences in `plan.md` describing what and why, then implement. No substeps.
- **Medium** (multiple components, design decisions, edge cases): write a plan in `plan.md` with requirements, affected files, key decisions, verification. Break into 3–5 steps.
- **Large** (new feature, cross-cutting, unclear scope): gather requirements and write a technical spec first (`requirements.md`, `spec.md` in `{@artifacts_path}/`). Then write `plan.md` with concrete steps referencing the spec.

**Skip planning and implement directly when** the task is trivial, or the user explicitly asks to "just do it" / gives a clear direct instruction.

To reflect the actual purpose of the first step, you can rename it to something more relevant (e.g., Planning, Investigation). Do NOT remove meta information like comments for any step.

Rule of thumb for step size: each step = a coherent unit of work (component, endpoint, test suite). Not too granular (single function), not too broad (entire feature). Unit tests are part of each step, not separate.

Update `{@artifacts_path}/plan.md`.


### [x] Step: script web
<!-- chat-id: be6e8c02-8d6e-427d-93e0-c859bad1403e -->

ecrit moi un script php nommée sous le repertoir web qui execute 
php Thelia thelia:dev:reloadDB
php setup/gpt.php

### [x] Step: php setup/gpt.php
<!-- chat-id: ffc2d451-53f8-431f-a3c7-75d59b28f300 -->

rajoute un autre fichier qui execute php setup/gpt.php
selement

### [x] Step: erreur
<!-- chat-id: e95287f1-780f-4503-8184-58c2aadd3e48 -->

fix 
=== php setup/gpt.php ===
X-Powered-By: PHP/4.4.9
Content-type: text/html



Parse error:  syntax error, unexpected T_DOUBLE_ARROW, expecting '(' in /home/mbfdevb/www/setup/gpt.php on line 20

Code retour : 255

### [x] Step: error
<!-- chat-id: 84abdd67-6c2b-4e14-b222-9f41925d8b11 -->

=== php setup/gpt.php ===
sh: /images/legacy/usr/local/php5.6/sbin/php-fpm: No such file or directory
Code retour : 127

### [x] Step: error 3
<!-- chat-id: 5a83b67f-6721-4d8a-8b7e-966532e5108e -->

=== php setup/gpt.php ===
X-Powered-By: PHP/4.4.9
Content-type: text/html



Parse error:  syntax error, unexpected T_DOUBLE_ARROW, expecting '(' in /home/mbfdevb/www/setup/gpt.php on line 20

Code retour : 255

### [x] Step: error 4
<!-- chat-id: 826d6ef9-0dae-498f-b79a-9564d7ee0ec1 -->

=== php setup/gpt.php ===
X-Powered-By: PHP/4.4.9
Content-type: text/html



Parse error:  syntax error, unexpected T_DOUBLE_ARROW, expecting '(' in /home/mbfdevb/www/setup/gpt.php on line 20

Code retour : 255
je ne c'est pas si il est exeutable sans php au debut on peux testé cette solution je pense
