# History GridView evaluation

## Acceptance criterion

Reproduce History's appearance, responsive layout, filtering, ordering, paging, links, escaping, and accessibility
using supported APIs and normal styling. Artificial cell content, HTML post-processing, vendor patches, and changing
test expectations to conceal differences do not count as a successful integration.

Byte-identical HTML and equivalent rendered UI are separate checks. The original manual renderer is the reference;
matching an earlier DataView prototype alone does not establish equivalence with that reference.

## Verified environment

- `yiisoft/yii-dataview`: installed `dev-optional-container`, commit `9c36d8ba423d034e7ef0486b450c12dc102b6f8c`.
- `yiisoft/data`: 2.0.0.
- PHP: 8.5.9. Repository support remains PHP 8.3, 8.4, and 8.5; this evaluation did not run all three runtimes.

## Improvements that preserve the existing prototype

- Pass the full filtered and ordered collection to an `OffsetPaginator`. Its actual page size and current page come
  from the existing `PageWindow` policy. GridView no longer receives an already sliced collection with an artificial
  page size chosen to prevent a second slice.
- Use the same paginator for visible rows, gauge scales, numbering, and footer counts.
- Remove the explicit `NullUrlParameterProvider`, which is already the installed version's default.
- Reuse `FilterRemoval` instead of duplicating filter-removal and page-reset logic.

Filtering, null-last ordering, sort links, controls, and the shared footer remain application-owned. Their presence
is an integration choice, not evidence that GridView lacks equivalent capabilities. This is not yet an evaluation
of an entirely native filtering/sorting/pager configuration.

## Reproducible observation: empty body cells lose column attributes

Run this with the installed package's Composer autoloader:

```php
use Yiisoft\Data\Reader\Iterable\IterableDataReader;
use Yiisoft\Yii\DataView\GridView\Column\DataColumn;
use Yiisoft\Yii\DataView\GridView\GridView;

echo GridView::widget()
    ->dataReader(new IterableDataReader([
        ['ip' => '127.0.0.1', 'method' => 'GET'],
        ['ip' => '', 'method' => ''],
    ]))
    ->layout('{items}')
    ->emptyCell('')
    ->columns(
        new DataColumn(property: 'ip', bodyClass: 'yii-debug-col-ip'),
        new DataColumn(property: 'method', bodyClass: 'method'),
    )
    ->render();
```

Observed body cells:

```html
<tr>
<td class="yii-debug-col-ip">127.0.0.1</td>
<td class="method">GET</td>
</tr>
<tr>
<td></td>
<td></td>
</tr>
```

The expected IP cell for the existing markup contract is `<td class="yii-debug-col-ip"></td>`.
`emptyCell('')` changes the placeholder, but does not preserve column attributes. `emptyCellAttributes()` is shared
by all empty cells; assigning the IP class there would incorrectly apply it to the empty Method cell too.

This matters with the current History stylesheet: at viewport widths up to 1366px, the IP class controls whether a
cell is hidden. Losing it is not just an HTML whitespace difference. It does not prove that the same appearance
cannot be achieved with ordinary CSS selectors; adapting the stylesheet is a separate integration option.

The current prototype still adds newlines around cell content to bypass this branch. It is retained while the
replacement is evaluated, not accepted as a valid solution. Therefore the prototype does **not yet pass** the
no-workarounds criterion.

Suggested upstream question: **Is replacing column-specific attributes on empty cells intentional? Would it make
sense to preserve them and merge explicitly configured empty-cell attributes?**

## Verification and limits

- Native-paginator refactor: 36 before/after scenarios produced byte-identical HTML, including 1205 rows, `all`,
  capped page sizes, out-of-range pages, filters, missing values, malformed input, and both sort directions.
- Focused History tests: 3 tests, 21 assertions passed.
- Full suite: 383 tests, 7 failures. Running the pre-refactor renderer produced the same 7 failing tests. They compare
  complete manual-renderer HTML against the DataView demos; expectations were not changed.
- This refactor's output comparisons are against the previous prototype, not a new responsive visual certification
  against the original grid. Exact equivalence and the removal of artificial empty-cell content remain open.
