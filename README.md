# opencolibri/starter-dev-kit-bundle

The admin of the dev-kit: build your local Sylius shop little by little. The dashboard sums up what the shop runs now;
one settings page per topic (storefront, catalog, locales and currencies, plugins, shop) holds its cards. Changing a
card shows the plan of `castor dev-kit:apply`, with the cost of each step, and **Apply** runs it. A Symfony bundle built on
[Sylius Shadcn Admin UI](https://github.com/Sylius/ShadcnAdminUi), duplicated from the sales kit admin
(`opencolibri/starter-sales-kit-bundle`) and kept apart on purpose: the sales kit spins up quick demos, the dev-kit
builds the final project.

Developed in the [colibri-starter](https://github.com/opencolibri/colibri-starter) monorepo (`src/DevKitBundle`), split
into the read-only [opencolibri/starter-dev-kit-bundle](https://github.com/opencolibri/starter-dev-kit-bundle)
repository. See the [dev-kit section of the documentation](https://starter.opencolibri.org/docs/dev-kit/).

## How it works

The admin never changes Sylius itself: every read and change goes through the `dev-kit:*` tasks of
`opencolibri/starter-dev-kit`, run by [castor-api](https://github.com/jeromegxj/castor-api) at the root of the project.

```
dev-kit admin (this bundle) ──HTTP──▶ castor-api ──▶ dev-kit:* tasks ──▶ local Sylius app
            └──── reads the progress of dev-kit:apply in .castor/import/var/.progress/ ────┘
```

| Task | Used for |
|---|---|
| `dev-kit:status --format=json` | what the shop runs now: the state every card starts from |
| `dev-kit:catalog` | what a card can pick: themes and their header placements, datasets, plugins |
| `dev-kit:apply --manifest-json=… --dry-run --format=json` | the plan of a change, shown in its card |
| `dev-kit:apply --manifest-json=… --yes` (async, lock `sylius:database`) | the run started by **Apply**, followed by `RunMonitor` |
| `dev-kit:history` | the history page, and the way back to any state |

## What the package ships

| Part | Where |
|---|---|
| castor-api client, run descriptions and progress | `src/Castor/`, `src/Import/RunProgress.php` |
| Live components: `ThemePicker`, `HeaderLayoutPicker`, `DatasetLoader`, `ShopNameEditor`, `LocalesPicker`, `PluginsPicker` (search as you type), `StateRestorer`, `RunMonitor` | `src/Twig/Components/`, on `PlansChangeTrait` |
| Anonymous components: `dev_kit:plan`, `dev_kit:action_button`, `dev_kit:message`, `dev_kit:header_preview` | `templates/components/ui/` |
| Dashboard summary, settings pages (`/settings/{page}`, `SettingsPages`), the page of a dataset (`/catalog/{slug}`: its products by collection, read from its files), history page, theme and dataset thumbnails | `config/app.php` (hooks), `src/Settings/`, `src/Controller/`, `templates/` |
| Data of the pages (`dev_kit_state()`, `dev_kit_catalog()`, `dev_kit_history()`) | `src/Twig/Extension/DevKitExtension.php` |
| Sidebar items (Dashboard, the settings pages, History) | `src/Menu/AdminMenuBuilder.php` |

## Run it locally

In a project with `opencolibri/starter-dev-kit` (the [Colibri Starter skeleton](https://github.com/opencolibri/starter-skeleton)
has it), `castor dev-kit:init` installs the admin: a small Symfony app (AssetMapper, Tailwind) in `dev-kit/`, copied
from `src/DevKit/resources/admin/`, run as a Symfony service of the stack (`castor docker:service:install dev-kit`
alone). It has no login, it only drives the local shop of the developer.

```bash
castor dev-kit:init                # starter.yaml, then the admin on https://dev-kit.<shop domain>/
castor api:serve-development       # castor-api, on http://127.0.0.1:8080, which the admin reaches through host.docker.internal
```

`dev-kit/.env.local` holds its settings: `CASTOR_API_URL`, `CASTOR_API_TOKEN`, `DEV_KIT_STOREFRONT_URL` and
`DEFAULT_URI`. `import_var_dir` points to the `.castor/import/var/` of the project, which the service mounts.

## Adding a settings page

A package or an instance adds its own page (custom checkout rules…) without touching the bundle: declare it, then fill
its hook with cards. The page shows in the sidebar by priority (the bundle's go from 500 to 100).

```yaml
opencolibri_dev_kit:
    pages:
        checkout:
            title: 'Checkout rules'
            subheader: 'What a customer must do before paying.'
            icon: 'lucide:shopping-cart'
            priority: 250

sylius_twig_hooks:
    hooks:
        'opencolibri_dev_kit.checkout.index.content.body':
            rules:
                template: 'admin/checkout/rules.html.twig'
```

A card that changes the shop uses `PlansChangeTrait` (a Live component with the current `state`, and a `change()` that
returns the wanted state) and renders `<twig:dev_kit:plan>`: it gets the plan and the **Apply** button for free, as
long as `dev-kit:apply` knows the setting. Its summary goes on the dashboard through the
`sylius_admin.dashboard.index.content` hook.

## Configuration

Every option defaults to an environment variable:

```yaml
opencolibri_dev_kit:
    castor_api:
        url: '%env(CASTOR_API_URL)%'                  # http://127.0.0.1:8080
        token: '%env(default::CASTOR_API_TOKEN)%'     # empty: no authentication
    storefront_url: '%env(DEV_KIT_STOREFRONT_URL)%'   # linked from the dashboard, e.g. https://app.test/
    import_var_dir: '%env(IMPORT_VAR_DIR)%'           # .castor/import/var of the project (progress of dev-kit:apply)
```

## Tests

```bash
vendor/bin/phpunit --testsuite dev-kit-bundle    # from the monorepo root
```

`tests/Application/Kernel.php` is a dev-kit instance with an `admin` user, where castor-api is replaced by
`tests/Support/FakeCastorApi.php` and the JSON of `tests/Fixtures/castor-api/`.
