# AI writing assistant for Open Core HR

A free, open source add-on for [Open Core HR](https://czappstudio.com/open-core-hr), the HR and field workforce management software by CZ App Studio.

It adds a **Write with AI** button under long text fields. It drafts, improves, shortens, formalises or translates your text, and nothing changes until you choose **Use this**.

**[Add-on page and screenshots](https://czappstudio.com/products/open-core-hr-ai-writing)**

## What it does

- Write with AI on announcements, job postings, performance goals, review comments, email templates and letter templates.
- Draft new text from a few words, or improve, shorten or formalise the text already in the field, with an optional note.
- Translate into any language enabled for your organisation.
- Merge fields such as `{{ employee_name }}` survive every suggestion; a suggestion that drops one is rejected.
- Email and letter suggestions keep their layout and are cleaned the same way the template is on save.
- Only the field text, your note and the company name are sent to your AI provider. No employee record is sent.

It uses the AI provider you connect in Open Core HR: Claude, Gemini, OpenAI or a self-hosted model. You pay your provider directly; CZ App Studio adds no AI charge.

## Requirements

- Open Core HR 2.0 or later, activated.
- An AI provider connected under **Settings → AI**.

## Install

1. In your activated installation, open **Settings → Modules**, find **AI writing assistant** and choose **Claim free**, then install it.
2. Connect your AI provider under **Settings → AI** and switch on **Writing help**.
3. Select **Write with AI** under a field, read the suggestion and choose **Use this**.

Updates arrive on the same Modules page. Installing from there is the supported route; this repository is the same code, published so you can read it, audit it and build on it.

## What is in this repository

| Path | Contents |
|---|---|
| `backend/Modules/AiWriting/app` | The writing agent and its prompt, the API controller, request validation, the writing actions and purposes |
| `backend/Modules/AiWriting/config` | The `use_ai_writing` permission and who gets it by default |
| `backend/Modules/AiWriting/routes` | `POST /api/v1/ai-writing` (signed-in users, 20 requests a minute) |
| `backend/Modules/AiWriting/resources/js` | The React slot that renders the Write with AI button; it is compiled with the Core web app |
| `backend/Modules/AiWriting/lang` | Translations in 25 languages |
| `backend/Modules/AiWriting/tests` | Feature tests; they run inside an Open Core HR installation |

The paths match the product, so the module sits at `Modules/AiWriting` in an installation. It is a [nwidart/laravel-modules](https://github.com/nWidart/laravel-modules) module (Laravel, Inertia, React) and depends on Core classes such as `App\Ai\FeatureAgent`, so it does not run on its own.

## What is open source here

This add-on is open source under the [MIT licence](LICENSE). Open Core HR itself (the Core this add-on runs on) is commercial software, supplied with source code to licence holders. It is not open source, and this repository does not include it.

## Bugs, ideas and contributions

- **Bugs:** open a support ticket from your [CZ App Studio account](https://czappstudio.com/account). Issues are switched off here so nothing gets missed.
- **Feature requests:** post them on the [public roadmap](https://czappstudio.com/roadmap).
- **Pull requests** are welcome. This repository is a mirror that is refreshed at each release, so accepted changes are applied in the product and appear here with the next release.
- **Security:** see [SECURITY.md](SECURITY.md).

## About Open Core HR

[Open Core HR](https://czappstudio.com/open-core-hr) is HR and field workforce management software by [CZ App Studio](https://czappstudio.com): employees, attendance, leave, expenses and approvals alongside visits, live field tracking and a mobile app, in 25+ languages. Run it on your own server, or use a private copy hosted and managed by us.

- [Features and pricing](https://czappstudio.com/pricing) · [All add-ons](https://czappstudio.com/addons) · [Changelog](https://czappstudio.com/changelog)
- [Documentation](https://czappstudio.com/docs), also for AI agents: [llms.txt](https://czappstudio.com/llms.txt) and an MCP server at `https://czappstudio.com/mcp`
