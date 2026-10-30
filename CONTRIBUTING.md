# Contributing

Contributions are **welcome** and will be fully **credited**.

Please read and understand the contribution guide before creating an issue or pull request.

## Etiquette

This project is open source, and as such, the maintainers give their free time to build and maintain the source code
held within. They make the code freely available in the hope that it will be of use to other developers. It would be
extremely unfair for them to suffer abuse or anger for their hard work.

Please be considerate towards maintainers when raising issues or presenting pull requests. Let's show the
world that developers are civilized and selfless people.

It's the duty of the maintainer to ensure that all submissions to the project are of sufficient
quality to benefit the project. Many developers have different skillsets, strengths, and weaknesses. Respect the maintainer's decision, and do not be upset or abusive if your submission is not used.

## Viability

When requesting or submitting new features, first consider whether it might be useful to others. Open
source projects are used by many developers, who may have entirely different needs to your own. Think about
whether or not your feature is likely to be used by other users of the project.

## Procedure

Before filing an issue:

- Attempt to replicate the problem, to ensure that it wasn't a coincidental incident.
- Check to make sure your feature suggestion isn't already present within the project.
- Check the pull requests tab to ensure that the bug doesn't have a fix in progress.
- Check the pull requests tab to ensure that the feature isn't already in progress.

Before submitting a pull request:

- Check the codebase to ensure that your feature doesn't already exist.
- Check the pull requests to ensure that another person hasn't already submitted the feature or fix.

## Requirements

If the project maintainer has any additional requirements, you will find them listed here.

- **Coding style** - Use Laravel Pint with the committed Laravel preset (`composer format`). CI checks formatting without changing files.

- **Add tests!** - Your patch won't be accepted if it doesn't have tests.

- **Document any change in behaviour** - Make sure the `README.md` and any other relevant documentation are kept up-to-date.

- **Consider our release cycle** - We try to follow [SemVer v2.0.0](https://semver.org/). Randomly breaking public APIs is not an option.

- **One pull request per feature** - If you want to do more than one thing, send multiple pull requests.

- **Send coherent history** - Make sure each individual commit in your pull request is meaningful. If you had to make multiple intermediate commits while developing, please [squash them](https://www.git-scm.com/book/en/v2/Git-Tools-Rewriting-History#Changing-Multiple-Commit-Messages) before submitting.

## Quality checks

Install development dependencies with `composer install`, then run `composer qa`
before submitting a pull request. It validates Composer metadata and PSR-4
loading, checks Pint formatting, runs PHPStan with Larastan at level 6 over `src/`,
runs the PHP tests, and audits installed dependencies for security advisories.
Static analysis targets PHP 8.3; CI tests PHP 8.3, 8.4 and 8.5 with Laravel 12 and 13.
Fix findings in code or precise type annotations rather than adding a baseline.

Run individual checks with `composer check-style`, `composer analyse`,
`composer test`, or `composer audit`. Apply PHP formatting with `composer format`.
The dependency audit requires network access. Generated tool caches and the
package's local Composer lock file are not committed.

## Commit messages and release notes

Use the same commit format as `core-panel-monorepo`:

```text
<emoji> <Type>: <concise imperative summary>
```

Write summaries in English so they can be used directly as release-note bullets.
Choose the type by the primary intent of the entire change. For example, the
Package Tools migration is `♻️ Refactoring: Register the package with Spatie Package Tools`.
Reserve `✨ Feature:` for an independently meaningful new capability.

| Prefix | Release-note section |
| --- | --- |
| `✨ Feature:` | Features |
| `🐛 Fix:`, `🚑 Hotfix:` | Fixes |
| `♻️ Refactoring:` | Refactoring |
| `🎨 Styling:`, `💄 UI:` | UI & Styling |
| `📝 Docs:` | Documentation |
| `🔒 Security:` | Security |
| `⚡ Performance:` | Performance |
| `⚠️ Deprecated:` | Deprecated |
| `🔥 Remove:` | Removed |
| `🔧 Chore:`, `📦 Deps:`, `⬆️ Deps:`, `⬇️ Deps:`, `🧪 Test:` | Chores |
| `🚀 Deploy:` | Deployment |

CI checks new commits in each push or pull request. Historical commits are not
rewritten. For compatibility with older history, the notes generator also
recognizes Core-Panel's plain prefixes such as `Feature:`, `Fix:`, and `Refactor:`.
Unrecognized messages appear under **Other Changes** rather than being omitted.
Merge subjects, generated `⚙️ Chore: set version to ...` messages, and release
trigger messages are excluded from the notes. Only nonempty sections are shown,
in the table's order; changes within a section follow commit order.

## Dependency updates

[Dependabot](.github/dependabot.yml) checks Composer and GitHub Actions every Monday. Patch and minor version updates are grouped per
ecosystem; major version updates are proposed individually. Composer updates
use `widen` to retain existing supported versions where possible. Review changes
to PHP and Laravel compatibility explicitly.

Dependabot commits use `📦 Deps:` to pass the existing commit convention checks.
Merge update pull requests after reviewing the changes and successful CI,
including the PHP and Laravel integration tests. Publication follows the explicit
release process below.

In the GitHub repository's security settings, enable the dependency graph,
Dependabot alerts and Dependabot security updates. These repository
settings are separate from `dependabot.yml`. The weekly schedule and update
groups above apply to version updates; security updates are handled separately.
See the [Dependabot configuration reference](https://docs.github.com/en/code-security/reference/supply-chain-security/dependabot-options-reference).

## Creating a release

Use the [release branch workflow](.release/README.md#release-branches): start
features from main, squash reviewed feature branches into release/X.Y.Z or
commit directly there without squashing. Leave version metadata and CHANGELOG.md
unchanged during ordinary development. Once the release scope is complete, run
`node .release/branches.mjs prepare` once and open a PR targeting main. If additional
commits follow, collect them and refresh preparation and checks at finalization;
do not regenerate the changelog after every commit.
Merge only through that reviewed PR, using a merge commit titled `🚀 Release: X.Y.Z`.
Direct main pushes/local release merges and administrator bypasses are forbidden.
The tag identifies the tested main merge. After successful publication and
verification, delete the integrated release branch; use a fresh branch per version.
Manual publication retries that prepared merge; it cannot choose another version.
Dependabot PRs must be retargeted to the active release branch before merging.

**Happy coding**!
