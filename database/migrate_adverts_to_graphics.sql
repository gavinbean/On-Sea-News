-- Migration script to update advert image paths from uploads/adverts/ to uploads/graphics/
-- Run this after renaming the directory on the server

UPDATE `bk_business_adverts` 
SET `banner_image` = REPLACE(`banner_image`, 'uploads/adverts/', 'uploads/graphics/')
WHERE `banner_image` LIKE 'uploads/adverts/%';

UPDATE `bk_business_adverts` 
SET `display_image` = REPLACE(`display_image`, 'uploads/adverts/', 'uploads/graphics/')
WHERE `display_image` LIKE 'uploads/adverts/%';

-- Also update the old advertisements table if it exists
UPDATE `bk_advertisements` 
SET `advert_image` = REPLACE(`advert_image`, 'uploads/adverts/', 'uploads/graphics/')
WHERE `advert_image` LIKE 'uploads/adverts/%';
