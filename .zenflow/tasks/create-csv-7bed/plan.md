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

### [x] Step: Implementation
<!-- chat-id: dd049fb5-78ae-4490-83a6-488849b595a7 -->

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

### [x] Step: import image
<!-- chat-id: 2a021f9a-de6d-4e6b-bc89-863dc2075f58 -->

tu analyse les fichier csv sous le repertoire setup\importzen 
et tu cheche chaque image tous les images que tu trouve sous les repertoire setup\electrohajkacem et ces sus repertoire 
les images que tu trouve qui correspondent au images dans le fichier csv tu les deplace sous les repertoire setup\importzen\images et ceux que tu ne trouve pas tuen crée un fichier txt et tu les ecrit dans ce fichier


### [x] Step: ask
<!-- chat-id: de0915e8-dcfd-40d9-aaea-1d295aab0624 -->

peux tu consulter le contenu du repertoire  C:\Mourad\www\electro\e juste a la racine pour ce task

### [x] Step: fix import
<!-- chat-id: dff0ff88-0565-427e-8862-72c9b244b334 -->

en exevutant sous mon contneeur docker 

#  php setup/import.php
Clearing tables
Tables cleared with success
start creating materials feature
materials feature created successfully
start creating colors attributes
colors attributes created with success
start creating brands
brands created successfully
start creating folders
Folders created successfully
start creating contents
Contents created successfully
start creating categories
error : Impossible to create an url if title is null

### [x] Step: structure thelia category sous category
<!-- chat-id: dc11983b-83d8-4d12-a7f1-800b165db4d9 -->

ici setup\thelia.sql tu as ma structure de la base de donnée thelia tu n'y touche pas , juste tu l'analyse pour que tu puissent faire en sorte de corriger les fichier csv sous le repertoire setup\import pour qu'il importe les donnée depuis le fichier setup\import.php en gardant les category avec leurs sous category respectif meme chose pour les autres fichier csv et je vous rappel que l'origine des donnée a importer sans exporter depuis ma base de donnée wordpress avec woocommerce que j'ai fournie l'export en format csv sous le repertoire setup\wordpresswoocomerce

### [ ] Step: langue currency
<!-- chat-id: 5f23422a-1234-47f8-bda0-1acb7f3fead0 -->

dans ce fichier que tu consultera selement setup\insert.sql.tpl 
tu as le donnée inserer dans d'autres tables de ma base de donnée 
je veux que les prix setup\import soit  sous la currency tnd 
en plus pour les tables du fichier setup\thelia.sql qui non pas un csv sous setup\import  tu verifie setup\wordpresswoocomerce si tu trouve des donnée qui peuvent etre imprter dans ces tableau tu leurs crée leurs propre csv tu importe la totaliter des donnée et tu adapte le fichier setup\import.php pour qu'il les importe
