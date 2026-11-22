-- =============================================================================
-- 2026-11 probe cleanup (MySQL)
--
-- Removes rows injected into production between 2026-11-16 and 2026-11-19
-- through the unauthenticated endpoints fixed in this release, and repairs
-- the few rows those injected events modified.
--
-- Every id below was identified from a copy of the production dump taken on
-- 2026-11-22. Each statement is additionally guarded by the timestamps/values
-- observed in that dump, so re-running the script is a no-op and rows that
-- changed since the dump are left untouched.
--
-- HOW TO RUN
--   1. Deploy the code fixes first (otherwise new junk can be injected).
--   2. Take a backup:  mysqldump --single-transaction <db> > backup-before-cleanup.sql
--   3. mysql <db> < database/cleanup/2026-11-probe-cleanup.sql
--      The script runs in a single transaction and prints row counts.
--      Review the counts in the final SELECTs; the script ends with COMMIT.
--      (Replace the final COMMIT with ROLLBACK for a dry run.)
--
-- NOT covered by this script (manual follow-up, see PR description):
--   * storage/app/claimed-dust.txt is inflated by 686 and
--     storage/app/burned-dust.txt by 5574 (DUST amounts from injected events).
--   * Users 5703, 5706, 5707, 5708, 5732 were created by the probe but use
--     real Flow addresses with on-chain/DB activity; they are kept (only their
--     sessions are revoked). Delete them manually after review if desired.
--   * 238 TerraKit pack rows were reserved (packs.user_id/assigned_at) by the
--     probe accounts. They are intentionally left reserved (assigned_at is kept;
--     the packs.user_id FK sets user_id to NULL for deleted users) so they stay
--     out of the sale pool. Review separately.
--   * sale_offers of items touched by injected Deposit events may have been
--     removed by the cleanup logic in ProcessGraffleEvents; re-sync listings
--     from chain for Flovatars 1, 42, 1234, 6114, 6715, Flobot 600,
--     components 1, 2, 10, 100 and collectibles 796, 797.
-- =============================================================================

START TRANSACTION;

-- -----------------------------------------------------------------------------
-- 1. Injected events (75 rows, ids 1755812-1755886).
--    None of them is a real Flow event: transaction ids are not valid/sealed
--    Flow transactions and block heights do not match the chain. No genuine
--    webhook event was received after 2026-02-23.
-- -----------------------------------------------------------------------------
DELETE FROM events
 WHERE id BETWEEN 1755812 AND 1755886
   AND graffle = 1
   AND created_at >= '2026-11-16 00:00:00'
   AND created_at <  '2026-11-20 00:00:00';

-- Any other row whose type is not a Flow event type, or that claims to come
-- from the 0x0000000000000000 address (catches junk inserted after the dump).
DELETE FROM events
 WHERE created_at >= '2026-11-16 00:00:00'
   AND (type NOT REGEXP '^A\\.[0-9a-f]{16}\\.' OR type LIKE 'A.0000000000000000.%');

-- Failed queue jobs produced by the injected events (776 rows in the dump).
DELETE FROM failed_jobs
 WHERE failed_at >= '2026-11-16 00:00:00'
   AND failed_at <  '2026-11-23 00:00:00'
   AND payload LIKE '%ProcessGraffleEvents%';

-- -----------------------------------------------------------------------------
-- 2. Fake sales created from injected events (prices 31337, 41337, 31337,
--    55555, 77777, 88888, 43217).
-- -----------------------------------------------------------------------------
DELETE FROM sales
 WHERE id IN (27308, 27309, 27310, 27311, 27312, 27313, 27314)
   AND created_at >= '2026-11-18 00:00:00'
   AND created_at <  '2026-11-20 00:00:00';

-- Fake listing created from an injected FlovatarComponentForSale event.
DELETE FROM sale_offers
 WHERE id = 51595
   AND created_at >= '2026-11-18 00:00:00'
   AND created_at <  '2026-11-20 00:00:00';

-- Pack row created by an injected FlovatarPack.Opened event (flow_id 999999999).
DELETE FROM packs
 WHERE id = 17397
   AND flow_id = 999999999
   AND created_at >= '2026-11-16 00:00:00'
   AND created_at <  '2026-11-17 00:00:00';

-- -----------------------------------------------------------------------------
-- 3. Repairs of rows modified by injected events.
--    Owners are restored from the last genuine Deposit event already stored in
--    the events table (verified on mainnet on 2026-11-22).
-- -----------------------------------------------------------------------------

-- Component 2: owner cleared by an injected Withdraw event.
UPDATE flovatar_components
   SET flow_address = (
       SELECT JSON_UNQUOTE(JSON_EXTRACT(e.flow_metadata, '$.to')) FROM events e
        WHERE e.type = 'A.921ea449dffec68a.FlovatarComponent.Deposit'
          AND e.created_at < '2026-11-16 00:00:00'
          AND JSON_EXTRACT(e.flow_metadata, '$.id') = 2
        ORDER BY e.block_id DESC, e.id DESC LIMIT 1)
 WHERE flow_id = 2
   AND flow_address IS NULL
   AND updated_at = '2026-11-19 01:34:39';

-- Component 10: owner overwritten by an injected Deposit event.
UPDATE flovatar_components
   SET flow_address = (
       SELECT JSON_UNQUOTE(JSON_EXTRACT(e.flow_metadata, '$.to')) FROM events e
        WHERE e.type = 'A.921ea449dffec68a.FlovatarComponent.Deposit'
          AND e.created_at < '2026-11-16 00:00:00'
          AND JSON_EXTRACT(e.flow_metadata, '$.id') = 10
        ORDER BY e.block_id DESC, e.id DESC LIMIT 1)
 WHERE flow_id = 10
   AND flow_address = '0xaaaabbbbccccdddd';

-- Component 100: falsely marked destroyed (and fake owner) by injected events.
-- Its last genuine event was a Withdraw (equipped), so the owner is NULL.
UPDATE flovatar_components
   SET destroyed = 0, flow_address = NULL
 WHERE flow_id = 100
   AND destroyed = 1
   AND flow_address = '0xaaaabbbbccccdddd';

-- Template 1 burned counter was incremented by the injected Destroyed event.
UPDATE flovatar_templates
   SET burned = burned - 1
 WHERE id = 1
   AND burned = 21
   AND updated_at = '2026-11-19 01:34:39';

-- Collectible 797: owner overwritten by an injected Deposit event.
UPDATE dust_collectibles
   SET flow_address = (
       SELECT JSON_UNQUOTE(JSON_EXTRACT(e.flow_metadata, '$.to')) FROM events e
        WHERE e.type = 'A.921ea449dffec68a.FlovatarDustCollectible.Deposit'
          AND e.created_at < '2026-11-16 00:00:00'
          AND JSON_EXTRACT(e.flow_metadata, '$.id') = 797
        ORDER BY e.block_id DESC, e.id DESC LIMIT 1)
 WHERE flow_id = 797
   AND flow_address = '0xaaaabbbbccccdddd';

-- -----------------------------------------------------------------------------
-- 4. Accounts created by the probe (users 5703-5732).
--    Deleted: email-registration accounts, malformed values and addresses that
--    are not valid mainnet accounts / have no activity (25 rows).
--    Kept for manual review (real Flow addresses with activity):
--    5703, 5706, 5707, 5708, 5732.
-- -----------------------------------------------------------------------------
DELETE FROM sessions
 WHERE user_id BETWEEN 5703 AND 5732;

DELETE FROM users
 WHERE id IN (5704, 5705, 5709, 5710, 5711, 5712, 5713, 5714, 5715, 5716,
              5717, 5718, 5719, 5720, 5721, 5722, 5723, 5724, 5725, 5726,
              5727, 5728, 5729, 5730, 5731)
   AND created_at >= '2026-11-16 00:00:00'
   AND created_at <  '2026-11-20 00:00:00';

-- -----------------------------------------------------------------------------
-- 5. Forged leaderboard scores (12 rows) and probe newsletter sign-ups
--    (65 rows; id 2399 is left for manual review).
-- -----------------------------------------------------------------------------
DELETE FROM game_scores
 WHERE id BETWEEN 571 AND 582
   AND created_at >= '2026-11-18 00:00:00'
   AND created_at <  '2026-11-20 00:00:00';

DELETE FROM subscribers
 WHERE id BETWEEN 2344 AND 2409
   AND id <> 2399
   AND created_at >= '2026-11-18 00:00:00'
   AND created_at <  '2026-11-20 00:00:00';

-- -----------------------------------------------------------------------------
-- Verification (all counts should be 0 except the kept users = 5).
-- -----------------------------------------------------------------------------
SELECT 'events since 2026-11-16' AS check_name, COUNT(*) AS remaining FROM events WHERE created_at >= '2026-11-16 00:00:00' AND id <= 1755886
UNION ALL SELECT 'fake sales', COUNT(*) FROM sales WHERE id BETWEEN 27308 AND 27314
UNION ALL SELECT 'probe users kept for review', COUNT(*) FROM users WHERE id BETWEEN 5703 AND 5732
UNION ALL SELECT 'forged game scores', COUNT(*) FROM game_scores WHERE id BETWEEN 571 AND 582
UNION ALL SELECT 'components with fake owner', COUNT(*) FROM flovatar_components WHERE flow_address = '0xaaaabbbbccccdddd'
UNION ALL SELECT 'collectibles with fake owner', COUNT(*) FROM dust_collectibles WHERE flow_address = '0xaaaabbbbccccdddd';

COMMIT;
