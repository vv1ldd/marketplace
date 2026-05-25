UPDATE marketplace.wildflow_catalogs
SET bussiness_id = 216576235
WHERE type = 'retailer_catalog'
  AND JSON_UNQUOTE(JSON_EXTRACT(data, '$.data.product.title')) REGEXP
      'Google play|Google Play|Apple|PlayStation|Xbox|Steam|PUBG New State|PUBG Mobile|Razer Gold|League Of Legends|RIOT ACCESS|Nintendo EShop|Nintendo Online|Minecraft|Amazon|Spotify|Fortnite|Blizzard|Tinder|EA Play';


SELECT JSON_EXTRACT(data, '$.data.product.title') as tite
FROM marketplace.wildflow_catalogs wc
WHERE type = 'retailer_catalog'
  AND JSON_UNQUOTE(JSON_EXTRACT(data, '$.data.product.title')) REGEXP
      'PlayStation|Xbox|Steam|PUBG New State|PUBG Mobile|Razer Gold|League Of Legends|RIOT ACCESS|Nintendo EShop|Nintendo Online|Minecraft|Amazon|Spotify|Fortnite|Blizzard|Tinder|EA Play';

#   and JSON_UNQUOTE(JSON_EXTRACT(data, '$.data.product.categories[0].name')) REGEXP
#       'PlayStation|Xbox|Steam|PUBG New State|PUBG Mobile|Razer Gold|League Of Legends|RIOT ACCESS|Nintendo EShop|Nintendo Online|Minecraft|Amazon|Spotify|Fortnite|Blizzard|Tinder Gold|Tinder|EA Play'
#   and JSON_UNQUOTE(JSON_EXTRACT(data, '$.data.product.categories[0].name')) not in
#       ('Eliza', 'EXAPUNKS', 'MOLEK-SYNTEZ', 'Opus Magnum', 'SHENZHEN I/O')
# ;

SELECT COUNT(*)
FROM marketplace.wildflow_catalogs
WHERE type = 'retailer_catalog';

# Eliza
# EXAPUNKS
# MOLEK-SYNTEZ
# Opus Magnum
# SHENZHEN I/O


SELECT DISTINCT
    r.code,
    r.name
FROM wildflow_catalogs wc

         JOIN JSON_TABLE(
    wc.data,
    '$.data.product.regions[*]'
    COLUMNS (
        code VARCHAR(10) PATH '$.code',
        name VARCHAR(255) PATH '$.name'
        )
              ) AS r

WHERE bussiness_id is not null
;


SELECT DISTINCT category
FROM wildflow_catalogs
WHERE category IS NOT NULL and bussiness_id is not null
ORDER BY category;
