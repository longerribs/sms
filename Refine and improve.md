Keep the existing behavior and structure where possible, but fix any visual inconsistencies and especially remove contrast issues between light and dark themes.

Core requirements:

* Improve the theme center UI and make it feel more intentional and consistent to toogle color codes and chnage them of the primary,secondary,success,info,warning,danger,custom,light and dark themes.
* Ensure light,custom,dark,primary,secondary,success,info,warning,danger themes have excellent color contrast, readability and acessibility.
* Remove the revoke API key action from the client side but dont change api/actions/revoke_key.php to be used later for admins only . Only admins should be able to revoke API keys.
* Show a copyable API key for the user on the frontend.
* When the user taps copy, show a clear confirmation toast/status at the top right that says “Copied to clipboard”.
* On the developer or profile section, show a combined API and SMS overview.
* This overview should include:

  * API usage
  * Last used / last called
  * Last credited
  * Total units consumed
  * Remaining units
* Add an API gateway status section showing:

  * Total requests
  * Total units used
* Add a top header on all pages that shows:

  * Profile details
  * Username
  * SMS units remaining
  * A profile tab
* When the profile tab is tapped, open a modal with a full profile overview.
also add more extensive documentation about the API and how to use it in the developer section.
 
Outside-the-box improvements to consider:

* Add a theme preview switcher so users can see light and dark mode before applying changes.
* Include a “system theme” option that follows device settings.
* Add subtle motion or transition animations when switching themes.
* Show usage trends with small charts or sparklines for API and SMS activity.
* Add a quick action row in the profile modal for copy key, view usage, and contact support.
* Make the header compact on mobile and richer on desktop.
* Add empty states and loading skeletons so the dashboard still feels polished when data is unavailable.
* Consider a notification if API usage is unusually high or units are running low.
* Add last updated timestamps for usage metrics so the data feels trustworthy.

Overall goal: make the experience clearer, safer, more modern, and easier to monitor at a glance while keeping the UI elegant across themes and devices.
After you are done provde additional conciderations on what to refine/build/add next!!!
