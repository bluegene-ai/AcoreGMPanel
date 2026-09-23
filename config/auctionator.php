<?php
/**
 * File: config/auctionator.php
 * Purpose: Panel-side description of the mod-auctionator (拍卖机器人) management module.
 *
 * The module itself lives on the worldserver side: `configs/modules/mod_auctionator.conf`
 * plus three world/characters tables and the `.auctionator` GM command family. This file
 * only tells the panel where those things are and which config keys may be written.
 *
 * Copy the overrides you need into `config/generated/auctionator.php` (that file is not
 * tracked); the generated file is merged over this one by Core\Config.
 */

return [
    // Worldserver root of the realm that runs the module. The conf path is resolved
    // relative to it (per-realm overrides below win).
    'server_root' => 'E:\\Server\\release\\80',
    'conf_file' => 'configs/modules/mod_auctionator.conf',
    'log_file' => 'logs/auctionator.log',

    // mod-auctionator is only deployed on the 80 realm. Other realms get a warning on the
    // dashboard and the write/SOAP endpoints answer 422 instead of touching another server.
    'supported_server_ids' => [1],
    'server_overrides' => [
        1 => [
            'server_root' => 'E:\\Server\\release\\80',
            'conf_file' => 'configs/modules/mod_auctionator.conf',
            'log_file' => 'logs/auctionator.log',
        ],
    ],

    // How many lines of logs/auctionator.log the dashboard shows.
    'log_tail_lines' => 40,

    // Table row limits for the item policy tab (the panel renders them as-is).
    'disabled_items_limit' => 300,
    'itemclass_limit' => 300,
    'gm_list_limit' => 300,

    // GM command constraints, mirrored from AuctionatorCommands.cpp (MinListingHours /
    // MaxListingHours / 200 items per ".auctionator add").
    'add_max_items' => 200,
    'listing_hours_min' => 1,
    'listing_hours_max' => 720,
    'auctions_per_run_max' => 1000,
    'max_per_cycle_max' => 1000,
    'price_max_copper' => 4294967295,

    /**
     * Every config key the panel is allowed to write, grouped for the settings form.
     *
     * type   : bool | int | float | string
     * label  : app.auctionator.fields.<label>
     * group  : app.auctionator.groups.<group>
     * min/max: accepted range (the module clamps these too; the panel must never write
     *          something the module would silently change, because the file would then
     *          disagree with what the server actually uses).
     */
    'fields' => [
        'Auctionator.Enabled' => ['group' => 'master', 'type' => 'bool', 'label' => 'enabled'],
        'Auctionator.Seller.BidOnly' => ['group' => 'master', 'type' => 'bool', 'label' => 'bid_only'],
        'Auctionator.CharacterId' => ['group' => 'master', 'type' => 'int', 'label' => 'character_id', 'min' => 0, 'max' => 4294967295],
        'Auctionator.CharacterGuid' => ['group' => 'master', 'type' => 'int', 'label' => 'character_guid', 'min' => 0, 'max' => 4294967295],

        'Auctionator.AllianceSeller.Enabled' => ['group' => 'alliance_seller', 'type' => 'bool', 'label' => 'enabled'],
        'Auctionator.AllianceSeller.MaxAuctions' => ['group' => 'alliance_seller', 'type' => 'int', 'label' => 'max_auctions', 'min' => 0, 'max' => 1000000],
        'Auctionator.AllianceSeller.CycleMinutes' => ['group' => 'alliance_seller', 'type' => 'int', 'label' => 'cycle_minutes', 'min' => 1, 'max' => 1440],

        'Auctionator.HordeSeller.Enabled' => ['group' => 'horde_seller', 'type' => 'bool', 'label' => 'enabled'],
        'Auctionator.HordeSeller.MaxAuctions' => ['group' => 'horde_seller', 'type' => 'int', 'label' => 'max_auctions', 'min' => 0, 'max' => 1000000],
        'Auctionator.HordeSeller.CycleMinutes' => ['group' => 'horde_seller', 'type' => 'int', 'label' => 'cycle_minutes', 'min' => 1, 'max' => 1440],

        'Auctionator.NeutralSeller.Enabled' => ['group' => 'neutral_seller', 'type' => 'bool', 'label' => 'enabled'],
        'Auctionator.NeutralSeller.MaxAuctions' => ['group' => 'neutral_seller', 'type' => 'int', 'label' => 'max_auctions', 'min' => 0, 'max' => 1000000],
        'Auctionator.NeutralSeller.CycleMinutes' => ['group' => 'neutral_seller', 'type' => 'int', 'label' => 'cycle_minutes', 'min' => 1, 'max' => 1440],

        'Auctionator.Seller.AuctionsPerRun' => ['group' => 'seller_common', 'type' => 'int', 'label' => 'auctions_per_run', 'min' => 0, 'max' => 1000],
        'Auctionator.Seller.DefaultPrice' => ['group' => 'seller_common', 'type' => 'int', 'label' => 'default_price', 'min' => 0, 'max' => 4294967295],
        'Auctionator.Seller.RandomizeStackSize' => ['group' => 'seller_common', 'type' => 'bool', 'label' => 'randomize_stack_size'],
        'Auctionator.Seller.BidStartModifier' => ['group' => 'seller_common', 'type' => 'float', 'label' => 'bid_start_modifier', 'min' => 0, 'max' => 1],
        'Auctionator.Seller.PreferMarketItems' => ['group' => 'seller_common', 'type' => 'bool', 'label' => 'prefer_market_items'],
        'Auctionator.Seller.ExcludeUnverifiedItems' => ['group' => 'seller_common', 'type' => 'bool', 'label' => 'exclude_unverified_items'],
        'Auctionator.Seller.MinPriceModifier' => ['group' => 'seller_common', 'type' => 'float', 'label' => 'min_price_modifier', 'min' => 0, 'max' => 1000],
        'Auctionator.Seller.MaxPriceModifier' => ['group' => 'seller_common', 'type' => 'float', 'label' => 'max_price_modifier', 'min' => 0, 'max' => 1000],

        'Auctionator.AllianceBidder.Enabled' => ['group' => 'alliance_bidder', 'type' => 'bool', 'label' => 'enabled'],
        'Auctionator.AllianceBidder.CycleMinutes' => ['group' => 'alliance_bidder', 'type' => 'int', 'label' => 'cycle_minutes', 'min' => 1, 'max' => 1440],
        'Auctionator.AllianceBidder.MaxPerCycle' => ['group' => 'alliance_bidder', 'type' => 'int', 'label' => 'max_per_cycle', 'min' => 0, 'max' => 1000],

        'Auctionator.HordeBidder.Enabled' => ['group' => 'horde_bidder', 'type' => 'bool', 'label' => 'enabled'],
        'Auctionator.HordeBidder.CycleMinutes' => ['group' => 'horde_bidder', 'type' => 'int', 'label' => 'cycle_minutes', 'min' => 1, 'max' => 1440],
        'Auctionator.HordeBidder.MaxPerCycle' => ['group' => 'horde_bidder', 'type' => 'int', 'label' => 'max_per_cycle', 'min' => 0, 'max' => 1000],

        'Auctionator.NeutralBidder.Enabled' => ['group' => 'neutral_bidder', 'type' => 'bool', 'label' => 'enabled'],
        'Auctionator.NeutralBidder.CycleMinutes' => ['group' => 'neutral_bidder', 'type' => 'int', 'label' => 'cycle_minutes', 'min' => 1, 'max' => 1440],
        'Auctionator.NeutralBidder.MaxPerCycle' => ['group' => 'neutral_bidder', 'type' => 'int', 'label' => 'max_per_cycle', 'min' => 0, 'max' => 1000],
        'Auctionator.Bidder.BidOnOwn' => ['group' => 'bidder_common', 'type' => 'bool', 'label' => 'bid_on_own'],

        'Auctionator.MarketData.MaxAgeDays' => ['group' => 'market', 'type' => 'int', 'label' => 'max_age_days', 'min' => 0, 'max' => 3650],
        'Auctionator.MarketData.ImportFile' => ['group' => 'market', 'type' => 'string', 'label' => 'import_file'],
        'Auctionator.MarketData.ImportSource' => ['group' => 'market', 'type' => 'string', 'label' => 'import_source'],
        'Auctionator.MarketData.ImportIntervalMinutes' => ['group' => 'market', 'type' => 'int', 'label' => 'import_interval_minutes', 'min' => 5, 'max' => 10080],
        'Auctionator.MarketData.ImportMaxRows' => ['group' => 'market', 'type' => 'int', 'label' => 'import_max_rows', 'min' => 1, 'max' => 1000000],
        'Auctionator.MarketData.RetentionDays' => ['group' => 'market', 'type' => 'int', 'label' => 'retention_days', 'min' => 1, 'max' => 3650],

        'Auctionator.Multipliers.Seller.Poor' => ['group' => 'multipliers_seller', 'type' => 'float', 'label' => 'multiplier_poor', 'min' => 0, 'max' => 1000],
        'Auctionator.Multipliers.Seller.Normal' => ['group' => 'multipliers_seller', 'type' => 'float', 'label' => 'multiplier_normal', 'min' => 0, 'max' => 1000],
        'Auctionator.Multipliers.Seller.Uncommon' => ['group' => 'multipliers_seller', 'type' => 'float', 'label' => 'multiplier_uncommon', 'min' => 0, 'max' => 1000],
        'Auctionator.Multipliers.Seller.Rare' => ['group' => 'multipliers_seller', 'type' => 'float', 'label' => 'multiplier_rare', 'min' => 0, 'max' => 1000],
        'Auctionator.Multipliers.Seller.Epic' => ['group' => 'multipliers_seller', 'type' => 'float', 'label' => 'multiplier_epic', 'min' => 0, 'max' => 1000],
        'Auctionator.Multipliers.Seller.Legendary' => ['group' => 'multipliers_seller', 'type' => 'float', 'label' => 'multiplier_legendary', 'min' => 0, 'max' => 1000],

        'Auctionator.Multipliers.Bidder.Poor' => ['group' => 'multipliers_bidder', 'type' => 'float', 'label' => 'multiplier_poor', 'min' => 0, 'max' => 1000],
        'Auctionator.Multipliers.Bidder.Normal' => ['group' => 'multipliers_bidder', 'type' => 'float', 'label' => 'multiplier_normal', 'min' => 0, 'max' => 1000],
        'Auctionator.Multipliers.Bidder.Uncommon' => ['group' => 'multipliers_bidder', 'type' => 'float', 'label' => 'multiplier_uncommon', 'min' => 0, 'max' => 1000],
        'Auctionator.Multipliers.Bidder.Rare' => ['group' => 'multipliers_bidder', 'type' => 'float', 'label' => 'multiplier_rare', 'min' => 0, 'max' => 1000],
        'Auctionator.Multipliers.Bidder.Epic' => ['group' => 'multipliers_bidder', 'type' => 'float', 'label' => 'multiplier_epic', 'min' => 0, 'max' => 1000],
        'Auctionator.Multipliers.Bidder.Legendary' => ['group' => 'multipliers_bidder', 'type' => 'float', 'label' => 'multiplier_legendary', 'min' => 0, 'max' => 1000],
    ],

    // Read-only keys worth showing next to the editable ones (the module has no config
    // entry for them, they are listed so the dashboard is self-explanatory).
    'notes' => [
        'listing_hours' => 12,
    ],
];
