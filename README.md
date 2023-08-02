# ChaosDonkey
Magento 2 module to cause chaos

Chaos Donkey can be ran via CLI or configured as a Magento Cron job.

Chaos Donkey will roll a D20 and decide your fate.

1: Critical failure
2-?: Indexers
?-?: Caches
?-?: High traffic to non-cached pages
?-?: ?
20: Critical success

These are some unlucky rolls

❯ bin/magento chaosdonkey:kick
ChaosDonkeyKick kicks your Magento. You rolled a 6
The donkeys are napping

    ~/Sites/magento  on   main ?12 ▓▒░──────────────────────────────────────────────────────────────────────────────────────────────────────────────────
❯ bin/magento chaosdonkey:kick
ChaosDonkeyKick kicks your Magento. You rolled a 1
Critical Failure! Better check all of your donkeys.

    ~/Sites/magento  on   main ?12 ▓▒░──────────────────────────────────────────────────────────────────────────────────────────────────────────────────
❯ bin/magento chaosdonkey:kick
ChaosDonkeyKick kicks your Magento. You rolled a 20
Critical Success! Yee Haw the donkeys are loose!
