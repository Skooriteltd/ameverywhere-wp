# Third-Party Services & Data Disclosures

AmEveryWhere operates under a strict data minimization philosophy. The plugin itself does **not** collect telemetry, track users, or "phone home" to our servers for general usage statistics.

However, to provide advanced SEO functionality, AmEveryWhere acts as a bridge to several external APIs. These APIs are entirely optional and are only invoked if a Site Administrator explicitly configures them.

If you utilize these features, you should disclose them in your site's Privacy Policy. AmEveryWhere automatically injects a template for this into the WordPress Privacy Policy Guide.

## Services That May Receive Data

| Feature | External Service | Data Transmitted | Privacy Policy |
| :--- | :--- | :--- | :--- |
| **LLM Writing Assistant** | OpenAI API (api.openai.com) | When explicitly triggered by an editor, the current post title, meta description, or content excerpts are sent to generate suggestions. | [OpenAI Privacy](https://openai.com/privacy) |
| **LLM Writing Assistant** | Anthropic API (api.anthropic.com) | Same as above, if Anthropic is selected instead of OpenAI. | [Anthropic Privacy](https://www.anthropic.com/privacy) |
| **Rank Tracking** | SerpApi (serpapi.com) | Your configured focus keywords are sent to retrieve SERP rankings. | [SerpApi Privacy](https://serpapi.com/privacy) |
| **PageSpeed Dashboard** | Google PageSpeed API | The URLs of your posts/pages are transmitted to fetch Core Web Vitals. | [Google Privacy](https://policies.google.com/privacy) |
| **Search Console** | Google Search Console API | Read-only OAuth2 access is used to fetch impressions, clicks, and CTR data. No data is written to Google. | [Google Privacy](https://policies.google.com/privacy) |
| **Instant Indexing** | Bing IndexNow (api.indexnow.org) | When a post is published/updated, its URL is sent to instantly notify Bing, Yandex, and Seznam. | [IndexNow Privacy](https://www.indexnow.org/) |
| **Instant Indexing** | Google Indexing API | If enabled, published URLs are sent to Google to accelerate crawling. | [Google Privacy](https://policies.google.com/privacy) |
| **AmEveryWhere API** | AmEveryWhere (api.ameverywhere.com) | If you lack a personal API key for Google APIs, heavy analytics requests (URLs only) are proxied through our API. | [AmEveryWhere Privacy](https://ameverywhere.com/privacy) |

## Data Retention & Storage
- **Ollama (Local AI):** If you configure a local Ollama instance, data never leaves your server.
- **404 Logs:** Missing URLs are logged in your local WordPress database to assist in redirect creation. By default, these logs are automatically purged after 90 days.
- **Audit Logs:** SEO setting changes are recorded in a local audit log for accountability. These are automatically purged after 365 days.
