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

### [ ] Step: clear C:\Mourad\www\electro and file lost file
<!-- chat-id: 40c8e5b7-64f4-448d-9c5f-5a329a768916 -->

tu vas travailler sous le repertoire C:\Mourad\www\electro 
tu ne touche pas a C:\Mourad\www\electro\electro236 et a ces sous repertoire 
pour les autre repertoire tu supprime tous les doublant qui existe dejat dans C:\Mourad\www\electro\electro236 ou les sous repertoire de C:\Mourad\www\electro\electro236 
si tu trouve une ou plusieur images de ce fichier setup\importzen\images_manquantes.txt dans les autres repertoire tu les deplace dans le repertoire 
setup\importzen\images


### [ ] Step: suppression du nom d'image en fie

tu garde les images que tu as trouver et qui doivent existé dans C:\Mourad\www\electro\electro236\setup\importzen\images et tu les suuprime du fichier C:\Mourad\www\electro\electro236\setup\importzen\images_manquantes.txt
