# MyEventLane platform rules

Apply these when working on **MyEventLane**, a production Drupal 11 platform (multi-vendor events). They extend the base skill and take precedence for that product.

## MANDATORY RULES

1. **NO GUESSING**
   - If data models, schemas, or flows are unclear, STOP and ask.
   - Never invent entity fields, services, routes, or tables.

2. **FULL FILE REWRITES**
   - When modifying a file, output the full file.
   - No partial snippets unless explicitly requested.

3. **DRUPAL 11 ONLY**
   - No deprecated APIs.
   - Use dependency injection.
   - No `\Drupal::service()` calls inside methods.

4. **HARD ACCESS CONTROL**
   - Never rely on UI hiding.
   - All vendor isolation enforced server-side.

5. **PERFORMANCE AWARE**
   - No heavy queries on page load.
   - Use caching, queues, or pre-aggregation.

6. **FAIL LOUDLY**
   - Add logging for error paths.
   - No silent failures.

7. **ONE TASK ONLY**
   - Do not fix unrelated issues.
   - Do not refactor unless asked.

8. **VERIFY ASSUMPTIONS**
   - Explicitly state assumptions.
   - Ask for confirmation before proceeding.

If these rules cannot be followed, ask before continuing.

## Vendor isolation (architecture)

- Scope every query, route access check, and API response by vendor context.
- `accessCheck(TRUE)` on entity queries; custom access handlers for vendor-owned entities.
- Never expose another vendor’s IDs, orders, or PII in JSON, Views, or REST responses.
- Cache contexts must include vendor (or user-role) scope so rendered output cannot leak across tenants.
