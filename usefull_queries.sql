UPDATE marketplace.wildflow_catalogs
SET bussiness_id = 216576235
WHERE type = 'retailer_catalog'
  AND JSON_UNQUOTE(JSON_EXTRACT(data, '$.data.product.title')) REGEXP
      'PlayStation|Xbox|Steam|PUBG New State|PUBG Mobile|Razer Gold|League Of Legends|RIOT ACCESS|Nintendo EShop|Nintendo Online|Minecraft|Amazon|Spotify|Fortnite|Blizzard|Tinder|EA Play';


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
WHERE type = 'retailer_catalog'

# Eliza
# EXAPUNKS
# MOLEK-SYNTEZ
# Opus Magnum
# SHENZHEN I/O

