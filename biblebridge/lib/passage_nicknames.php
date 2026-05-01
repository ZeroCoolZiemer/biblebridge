<?php
/**
 * Well-known Bible passage nicknames → exact references.
 * Used by search to resolve queries like "beatitudes" → Matthew 5:3-12.
 * All references verified against the KJV database.
 */
global $PASSAGE_NICKNAMES;
$PASSAGE_NICKNAMES = [
    // ── Teachings ──────────────────────────────────────────
    'beatitudes'             => ['book_id' => 40, 'chapter' => 5,  'verse_start' => 3,  'verse_end' => 12],
    'the beatitudes'         => ['book_id' => 40, 'chapter' => 5,  'verse_start' => 3,  'verse_end' => 12],
    'blessed are'            => ['book_id' => 40, 'chapter' => 5,  'verse_start' => 3,  'verse_end' => 12],

    'lords prayer'           => ['book_id' => 40, 'chapter' => 6,  'verse_start' => 9,  'verse_end' => 13],
    "lord's prayer"          => ['book_id' => 40, 'chapter' => 6,  'verse_start' => 9,  'verse_end' => 13],
    'the lords prayer'       => ['book_id' => 40, 'chapter' => 6,  'verse_start' => 9,  'verse_end' => 13],
    "the lord's prayer"      => ['book_id' => 40, 'chapter' => 6,  'verse_start' => 9,  'verse_end' => 13],
    'our father'             => ['book_id' => 40, 'chapter' => 6,  'verse_start' => 9,  'verse_end' => 13],
    'our father prayer'      => ['book_id' => 40, 'chapter' => 6,  'verse_start' => 9,  'verse_end' => 13],

    'great commission'       => ['book_id' => 40, 'chapter' => 28, 'verse_start' => 18, 'verse_end' => 20],
    'the great commission'   => ['book_id' => 40, 'chapter' => 28, 'verse_start' => 18, 'verse_end' => 20],

    'sermon on the mount'    => ['book_id' => 40, 'chapter' => 5,  'verse_start' => 1,  'verse_end' => 48],
    'the sermon on the mount'=> ['book_id' => 40, 'chapter' => 5,  'verse_start' => 1,  'verse_end' => 48],

    'golden rule'            => ['book_id' => 40, 'chapter' => 7,  'verse_start' => 12, 'verse_end' => 12],
    'the golden rule'        => ['book_id' => 40, 'chapter' => 7,  'verse_start' => 12, 'verse_end' => 12],
    'do unto others'         => ['book_id' => 40, 'chapter' => 7,  'verse_start' => 12, 'verse_end' => 12],

    // ── Parables ───────────────────────────────────────────
    'prodigal son'           => ['book_id' => 42, 'chapter' => 15, 'verse_start' => 11, 'verse_end' => 32],
    'the prodigal son'       => ['book_id' => 42, 'chapter' => 15, 'verse_start' => 11, 'verse_end' => 32],
    'parable of the prodigal son' => ['book_id' => 42, 'chapter' => 15, 'verse_start' => 11, 'verse_end' => 32],

    'good samaritan'         => ['book_id' => 42, 'chapter' => 10, 'verse_start' => 25, 'verse_end' => 37],
    'the good samaritan'     => ['book_id' => 42, 'chapter' => 10, 'verse_start' => 25, 'verse_end' => 37],
    'parable of the good samaritan' => ['book_id' => 42, 'chapter' => 10, 'verse_start' => 25, 'verse_end' => 37],

    'parable of the sower'   => ['book_id' => 40, 'chapter' => 13, 'verse_start' => 1,  'verse_end' => 23],
    'the sower'              => ['book_id' => 40, 'chapter' => 13, 'verse_start' => 1,  'verse_end' => 23],
    'sower and the seed'     => ['book_id' => 40, 'chapter' => 13, 'verse_start' => 1,  'verse_end' => 23],

    'lost sheep'             => ['book_id' => 42, 'chapter' => 15, 'verse_start' => 1,  'verse_end' => 7],
    'the lost sheep'         => ['book_id' => 42, 'chapter' => 15, 'verse_start' => 1,  'verse_end' => 7],
    'parable of the lost sheep' => ['book_id' => 42, 'chapter' => 15, 'verse_start' => 1,  'verse_end' => 7],

    'mustard seed'           => ['book_id' => 40, 'chapter' => 13, 'verse_start' => 31, 'verse_end' => 32],
    'the mustard seed'       => ['book_id' => 40, 'chapter' => 13, 'verse_start' => 31, 'verse_end' => 32],
    'parable of the mustard seed' => ['book_id' => 40, 'chapter' => 13, 'verse_start' => 31, 'verse_end' => 32],

    'rich man and lazarus'   => ['book_id' => 42, 'chapter' => 16, 'verse_start' => 19, 'verse_end' => 31],
    'the rich man and lazarus' => ['book_id' => 42, 'chapter' => 16, 'verse_start' => 19, 'verse_end' => 31],
    'lazarus and the rich man' => ['book_id' => 42, 'chapter' => 16, 'verse_start' => 19, 'verse_end' => 31],

    'ten virgins'            => ['book_id' => 40, 'chapter' => 25, 'verse_start' => 1,  'verse_end' => 13],
    'the ten virgins'        => ['book_id' => 40, 'chapter' => 25, 'verse_start' => 1,  'verse_end' => 13],
    'parable of the ten virgins' => ['book_id' => 40, 'chapter' => 25, 'verse_start' => 1,  'verse_end' => 13],
    'wise and foolish virgins' => ['book_id' => 40, 'chapter' => 25, 'verse_start' => 1,  'verse_end' => 13],

    'parable of the talents' => ['book_id' => 40, 'chapter' => 25, 'verse_start' => 14, 'verse_end' => 30],
    'the talents'            => ['book_id' => 40, 'chapter' => 25, 'verse_start' => 14, 'verse_end' => 30],

    'wheat and tares'        => ['book_id' => 40, 'chapter' => 13, 'verse_start' => 24, 'verse_end' => 30],
    'the wheat and tares'    => ['book_id' => 40, 'chapter' => 13, 'verse_start' => 24, 'verse_end' => 30],
    'wheat and weeds'        => ['book_id' => 40, 'chapter' => 13, 'verse_start' => 24, 'verse_end' => 30],
    'parable of the tares'   => ['book_id' => 40, 'chapter' => 13, 'verse_start' => 24, 'verse_end' => 30],

    // ── Famous Passages ────────────────────────────────────
    'love chapter'           => ['book_id' => 46, 'chapter' => 13, 'verse_start' => 1,  'verse_end' => 13],
    'the love chapter'       => ['book_id' => 46, 'chapter' => 13, 'verse_start' => 1,  'verse_end' => 13],
    'love is patient'        => ['book_id' => 46, 'chapter' => 13, 'verse_start' => 1,  'verse_end' => 13],
    'charity chapter'        => ['book_id' => 46, 'chapter' => 13, 'verse_start' => 1,  'verse_end' => 13],

    'armor of god'           => ['book_id' => 49, 'chapter' => 6,  'verse_start' => 10, 'verse_end' => 18],
    'the armor of god'       => ['book_id' => 49, 'chapter' => 6,  'verse_start' => 10, 'verse_end' => 18],
    'armour of god'          => ['book_id' => 49, 'chapter' => 6,  'verse_start' => 10, 'verse_end' => 18],
    'whole armor of god'     => ['book_id' => 49, 'chapter' => 6,  'verse_start' => 10, 'verse_end' => 18],
    'whole armour of god'    => ['book_id' => 49, 'chapter' => 6,  'verse_start' => 10, 'verse_end' => 18],

    'fruit of the spirit'    => ['book_id' => 48, 'chapter' => 5,  'verse_start' => 22, 'verse_end' => 23],
    'fruits of the spirit'   => ['book_id' => 48, 'chapter' => 5,  'verse_start' => 22, 'verse_end' => 23],
    'fruit of the holy spirit' => ['book_id' => 48, 'chapter' => 5,  'verse_start' => 22, 'verse_end' => 23],

    'faith chapter'          => ['book_id' => 58, 'chapter' => 11, 'verse_start' => 1,  'verse_end' => 40],
    'hall of faith'          => ['book_id' => 58, 'chapter' => 11, 'verse_start' => 1,  'verse_end' => 40],
    'heroes of faith'        => ['book_id' => 58, 'chapter' => 11, 'verse_start' => 1,  'verse_end' => 40],

    'psalm 23'               => ['book_id' => 19, 'chapter' => 23, 'verse_start' => 1,  'verse_end' => 6],
    'the lord is my shepherd' => ['book_id' => 19, 'chapter' => 23, 'verse_start' => 1,  'verse_end' => 6],
    'lord is my shepherd'    => ['book_id' => 19, 'chapter' => 23, 'verse_start' => 1,  'verse_end' => 6],
    'shepherds psalm'        => ['book_id' => 19, 'chapter' => 23, 'verse_start' => 1,  'verse_end' => 6],
    "shepherd's psalm"       => ['book_id' => 19, 'chapter' => 23, 'verse_start' => 1,  'verse_end' => 6],
    'shepherd psalm'         => ['book_id' => 19, 'chapter' => 23, 'verse_start' => 1,  'verse_end' => 6],

    'valley of dry bones'    => ['book_id' => 26, 'chapter' => 37, 'verse_start' => 1,  'verse_end' => 14],
    'dry bones'              => ['book_id' => 26, 'chapter' => 37, 'verse_start' => 1,  'verse_end' => 14],
    'ezekiel dry bones'      => ['book_id' => 26, 'chapter' => 37, 'verse_start' => 1,  'verse_end' => 14],

    'romans road'            => ['book_id' => 45, 'chapter' => 3,  'verse_start' => 23, 'verse_end' => 23],
    'the romans road'        => ['book_id' => 45, 'chapter' => 3,  'verse_start' => 23, 'verse_end' => 23],

    'proverbs 31 woman'      => ['book_id' => 20, 'chapter' => 31, 'verse_start' => 10, 'verse_end' => 31],
    'virtuous woman'         => ['book_id' => 20, 'chapter' => 31, 'verse_start' => 10, 'verse_end' => 31],
    'the virtuous woman'     => ['book_id' => 20, 'chapter' => 31, 'verse_start' => 10, 'verse_end' => 31],
    'wife of noble character' => ['book_id' => 20, 'chapter' => 31, 'verse_start' => 10, 'verse_end' => 31],
    'proverbs 31 wife'       => ['book_id' => 20, 'chapter' => 31, 'verse_start' => 10, 'verse_end' => 31],

    // ── Events ─────────────────────────────────────────────
    'creation'               => ['book_id' => 1,  'chapter' => 1,  'verse_start' => 1,  'verse_end' => 31],
    'the creation'           => ['book_id' => 1,  'chapter' => 1,  'verse_start' => 1,  'verse_end' => 31],
    'creation story'         => ['book_id' => 1,  'chapter' => 1,  'verse_start' => 1,  'verse_end' => 31],
    'in the beginning'       => ['book_id' => 1,  'chapter' => 1,  'verse_start' => 1,  'verse_end' => 31],

    'the fall'               => ['book_id' => 1,  'chapter' => 3,  'verse_start' => 1,  'verse_end' => 24],
    'the fall of man'        => ['book_id' => 1,  'chapter' => 3,  'verse_start' => 1,  'verse_end' => 24],
    'fall of man'            => ['book_id' => 1,  'chapter' => 3,  'verse_start' => 1,  'verse_end' => 24],
    'adam and eve'            => ['book_id' => 1,  'chapter' => 3,  'verse_start' => 1,  'verse_end' => 24],
    'original sin'           => ['book_id' => 1,  'chapter' => 3,  'verse_start' => 1,  'verse_end' => 24],
    'forbidden fruit'        => ['book_id' => 1,  'chapter' => 3,  'verse_start' => 1,  'verse_end' => 24],

    'the flood'              => ['book_id' => 1,  'chapter' => 6,  'verse_start' => 1,  'verse_end' => 22],
    "noah's flood"           => ['book_id' => 1,  'chapter' => 6,  'verse_start' => 1,  'verse_end' => 22],
    'noahs flood'            => ['book_id' => 1,  'chapter' => 6,  'verse_start' => 1,  'verse_end' => 22],
    'noah and the flood'     => ['book_id' => 1,  'chapter' => 6,  'verse_start' => 1,  'verse_end' => 22],
    "noah's ark"             => ['book_id' => 1,  'chapter' => 6,  'verse_start' => 1,  'verse_end' => 22],
    'noahs ark'              => ['book_id' => 1,  'chapter' => 6,  'verse_start' => 1,  'verse_end' => 22],

    'ten commandments'       => ['book_id' => 2,  'chapter' => 20, 'verse_start' => 1,  'verse_end' => 17],
    'the ten commandments'   => ['book_id' => 2,  'chapter' => 20, 'verse_start' => 1,  'verse_end' => 17],
    '10 commandments'        => ['book_id' => 2,  'chapter' => 20, 'verse_start' => 1,  'verse_end' => 17],

    'david and goliath'      => ['book_id' => 9,  'chapter' => 17, 'verse_start' => 1,  'verse_end' => 58],
    'david vs goliath'       => ['book_id' => 9,  'chapter' => 17, 'verse_start' => 1,  'verse_end' => 58],
    'goliath'                => ['book_id' => 9,  'chapter' => 17, 'verse_start' => 1,  'verse_end' => 58],

    'christmas story'        => ['book_id' => 42, 'chapter' => 2,  'verse_start' => 1,  'verse_end' => 20],
    'the christmas story'    => ['book_id' => 42, 'chapter' => 2,  'verse_start' => 1,  'verse_end' => 20],
    'birth of jesus'         => ['book_id' => 42, 'chapter' => 2,  'verse_start' => 1,  'verse_end' => 20],
    'nativity'               => ['book_id' => 42, 'chapter' => 2,  'verse_start' => 1,  'verse_end' => 20],
    'the nativity'           => ['book_id' => 42, 'chapter' => 2,  'verse_start' => 1,  'verse_end' => 20],

    'crucifixion'            => ['book_id' => 40, 'chapter' => 27, 'verse_start' => 32, 'verse_end' => 56],
    'the crucifixion'        => ['book_id' => 40, 'chapter' => 27, 'verse_start' => 32, 'verse_end' => 56],
    'death of jesus'         => ['book_id' => 40, 'chapter' => 27, 'verse_start' => 32, 'verse_end' => 56],

    'resurrection'           => ['book_id' => 40, 'chapter' => 28, 'verse_start' => 1,  'verse_end' => 10],
    'the resurrection'       => ['book_id' => 40, 'chapter' => 28, 'verse_start' => 1,  'verse_end' => 10],
    'resurrection of jesus'  => ['book_id' => 40, 'chapter' => 28, 'verse_start' => 1,  'verse_end' => 10],
    'empty tomb'             => ['book_id' => 40, 'chapter' => 28, 'verse_start' => 1,  'verse_end' => 10],

    'ascension'              => ['book_id' => 44, 'chapter' => 1,  'verse_start' => 6,  'verse_end' => 11],
    'the ascension'          => ['book_id' => 44, 'chapter' => 1,  'verse_start' => 6,  'verse_end' => 11],
    'ascension of jesus'     => ['book_id' => 44, 'chapter' => 1,  'verse_start' => 6,  'verse_end' => 11],

    'day of pentecost'       => ['book_id' => 44, 'chapter' => 2,  'verse_start' => 1,  'verse_end' => 13],
    'pentecost'              => ['book_id' => 44, 'chapter' => 2,  'verse_start' => 1,  'verse_end' => 13],
    'the day of pentecost'   => ['book_id' => 44, 'chapter' => 2,  'verse_start' => 1,  'verse_end' => 13],
    'tongues of fire'        => ['book_id' => 44, 'chapter' => 2,  'verse_start' => 1,  'verse_end' => 13],

    'burning bush'           => ['book_id' => 2,  'chapter' => 3,  'verse_start' => 1,  'verse_end' => 15],
    'the burning bush'       => ['book_id' => 2,  'chapter' => 3,  'verse_start' => 1,  'verse_end' => 15],
    'moses and the burning bush' => ['book_id' => 2,  'chapter' => 3,  'verse_start' => 1,  'verse_end' => 15],

    'crossing the red sea'   => ['book_id' => 2,  'chapter' => 14, 'verse_start' => 1,  'verse_end' => 31],
    'parting of the red sea' => ['book_id' => 2,  'chapter' => 14, 'verse_start' => 1,  'verse_end' => 31],
    'red sea crossing'       => ['book_id' => 2,  'chapter' => 14, 'verse_start' => 1,  'verse_end' => 31],
    'parting the red sea'    => ['book_id' => 2,  'chapter' => 14, 'verse_start' => 1,  'verse_end' => 31],

    'daniel in the lions den'  => ['book_id' => 27, 'chapter' => 6,  'verse_start' => 1,  'verse_end' => 28],
    "daniel in the lion's den" => ['book_id' => 27, 'chapter' => 6,  'verse_start' => 1,  'verse_end' => 28],
    'lions den'              => ['book_id' => 27, 'chapter' => 6,  'verse_start' => 1,  'verse_end' => 28],
    "lion's den"             => ['book_id' => 27, 'chapter' => 6,  'verse_start' => 1,  'verse_end' => 28],
    'the lions den'          => ['book_id' => 27, 'chapter' => 6,  'verse_start' => 1,  'verse_end' => 28],

    'jonah and the whale'    => ['book_id' => 32, 'chapter' => 1,  'verse_start' => 1,  'verse_end' => 17],
    'jonah and the fish'     => ['book_id' => 32, 'chapter' => 1,  'verse_start' => 1,  'verse_end' => 17],

    'tower of babel'         => ['book_id' => 1,  'chapter' => 11, 'verse_start' => 1,  'verse_end' => 9],
    'the tower of babel'     => ['book_id' => 1,  'chapter' => 11, 'verse_start' => 1,  'verse_end' => 9],
    'babel'                  => ['book_id' => 1,  'chapter' => 11, 'verse_start' => 1,  'verse_end' => 9],

    // ── Prayers / Songs ────────────────────────────────────
    'magnificat'             => ['book_id' => 42, 'chapter' => 1,  'verse_start' => 46, 'verse_end' => 55],
    'the magnificat'         => ['book_id' => 42, 'chapter' => 1,  'verse_start' => 46, 'verse_end' => 55],
    "mary's song"            => ['book_id' => 42, 'chapter' => 1,  'verse_start' => 46, 'verse_end' => 55],
    'marys song'             => ['book_id' => 42, 'chapter' => 1,  'verse_start' => 46, 'verse_end' => 55],
    'song of mary'           => ['book_id' => 42, 'chapter' => 1,  'verse_start' => 46, 'verse_end' => 55],

    'shema'                  => ['book_id' => 5,  'chapter' => 6,  'verse_start' => 4,  'verse_end' => 9],
    'the shema'              => ['book_id' => 5,  'chapter' => 6,  'verse_start' => 4,  'verse_end' => 9],
    'hear o israel'          => ['book_id' => 5,  'chapter' => 6,  'verse_start' => 4,  'verse_end' => 9],

    'aaronic blessing'       => ['book_id' => 4,  'chapter' => 6,  'verse_start' => 24, 'verse_end' => 26],
    'the aaronic blessing'   => ['book_id' => 4,  'chapter' => 6,  'verse_start' => 24, 'verse_end' => 26],
    'priestly blessing'      => ['book_id' => 4,  'chapter' => 6,  'verse_start' => 24, 'verse_end' => 26],
    'the priestly blessing'  => ['book_id' => 4,  'chapter' => 6,  'verse_start' => 24, 'verse_end' => 26],
    'aaronic benediction'    => ['book_id' => 4,  'chapter' => 6,  'verse_start' => 24, 'verse_end' => 26],
    'the lord bless you'     => ['book_id' => 4,  'chapter' => 6,  'verse_start' => 24, 'verse_end' => 26],

    'prayer of jabez'        => ['book_id' => 13, 'chapter' => 4,  'verse_start' => 10, 'verse_end' => 10],
    'the prayer of jabez'    => ['book_id' => 13, 'chapter' => 4,  'verse_start' => 10, 'verse_end' => 10],
    'jabez prayer'           => ['book_id' => 13, 'chapter' => 4,  'verse_start' => 10, 'verse_end' => 10],
    "jabez's prayer"         => ['book_id' => 13, 'chapter' => 4,  'verse_start' => 10, 'verse_end' => 10],

    // ── Well-known Single Verses ───────────────────────────
    'for god so loved'       => ['book_id' => 43, 'chapter' => 3,  'verse_start' => 16, 'verse_end' => 16],
    'for god so loved the world' => ['book_id' => 43, 'chapter' => 3,  'verse_start' => 16, 'verse_end' => 16],

    'for i know the plans'   => ['book_id' => 24, 'chapter' => 29, 'verse_start' => 11, 'verse_end' => 11],
    'i know the plans'       => ['book_id' => 24, 'chapter' => 29, 'verse_start' => 11, 'verse_end' => 11],

    'i can do all things'    => ['book_id' => 50, 'chapter' => 4,  'verse_start' => 13, 'verse_end' => 13],

    'be still and know'      => ['book_id' => 19, 'chapter' => 46, 'verse_start' => 10, 'verse_end' => 10],
    'be still and know that i am god' => ['book_id' => 19, 'chapter' => 46, 'verse_start' => 10, 'verse_end' => 10],
];
