# Configuration
- Slack Channel 
- Slack Bot Token
- Paypal.me Link

# Features
When the orders are stopped ([Mettware Plugin](https://github.com/HoelShare/mettware-sw6)) a notifcation will be send to the configured channel.

# Command `mw:invoice`
Send a reminder for all open orders to the Slack ID (Needs to be configured as additionalAddress 1).

## Options
`-u` - Slack Id(s) which will get every invoice as a copy
`-f` - Filter only this Slack ID will get notified (perfect for testing, or if a person don't pay).
