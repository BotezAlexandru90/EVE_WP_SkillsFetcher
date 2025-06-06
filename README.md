# EVE_WP_SkillsFetcher
EVE Online Skill Integration for WordPress

This WordPress plugin allows users to authenticate their EVE Online characters via EVE SSO (OAuth 2.0), extracting and storing their skill data within your WordPress site. It's designed to support the common EVE Online player structure of having a "Main" character and multiple "Alt" characters, all linked to a single WordPress user account.

The primary goal is to collect EVE character skill information for review by site administrators, while providing a seamless authentication experience for users.
Core Functionality:

- Player-Centric Model: A single WordPress user account can represent an EVE Online player.
- Main Character Designation: The first EVE character authenticated by a WordPress user (or a visitor for whom an account is auto-created) is typically designated as their "Main."
- Alt Character Linking: Logged-in WordPress users can subsequently authenticate additional EVE characters, which are linked as "Alts" to their main character's WordPress account.
- ESI Integration: Fetches character information and skill data (skill IDs, levels, skill points) directly from the EVE Swagger Interface (ESI).
- Secure Data Storage: All EVE character data (including access/refresh tokens and skill information) is stored securely as WordPress user metadata.

Key Features:

- EVE Online SSO Authentication:
  - Users can link their EVE characters using the official EVE SSO.
  - Supports automatic WordPress user account creation for new visitors upon their first successful EVE authentication.

- Skill Extraction & Display:
  - Fetches detailed skill lists and total skill points for each authenticated character.
  - Resolves skill IDs to their human-readable names using ESI (with caching).

- Main & Alt Character Management:
  - Users can manage their linked main and alt characters via a dedicated page in their WordPress admin profile area.
  - Shortcode [eve_sso_login_button] for easy front-end integration, allowing users to:
    - Authenticate their first (Main) EVE character.
      - Authenticate additional Alt EVE characters if already linked with a Main.
      - Re-authenticate/Switch their Main character.
     
- Doctrine ships management:
  - Add/Edit/Delete doctrine ships
  - Visual indication on each character with what ships he character can fly/use.
    
- Wallet History analysis:
  - Corporation logo for easy identification of specific corporation links
  - Blue and Bold characters are Authenticated Character. This is a character (main or alt) that is registered on your website.
  - Orange: An Unauthenticated Character. This is any other player or NPC in EVE Online that is not registered on your website.
  - Filtering system
  - Built a beautiful ISK history chart to visualize the data.
    - 90/30/7 days timeframe to analyze
    - Individual chart visualization for each character linked to the Main.

Administrator Tools & Oversight:
- Plugin Settings Page: For configuring EVE Application Client ID, Secret Key, and Scopes. Accessible by Editors and Administrators.
- Centralized User Skill Viewing: A dedicated admin page to list all WordPress users who have linked EVE characters.
- Hierarchical Character Display: Admins can view a specific WordPress user's main character and all their linked alts in a clear, hierarchical structure.
- Detailed Skill Inspection: Admins can view the full skill list for any main or alt character.
  
- Character Management Tools (Admin-only):
  - Promote Alt to Main: For a specific WordPress user, an admin can promote one of their alts to become their new main character (the old main becomes an alt).
  - Remove Alt: Admins can remove an alt character from a WordPress user's account.
    
  - Reassign Character to Different User:
    - Admins can move an alt character from one WordPress user to become an alt of a different WordPress user's main character.
    - Admins can move a "solo" main character (one with no alts under it) from one WordPress user to become an alt of a different WordPress user's main character (the original user will then have no main linked).

  - Automatic Data Refresh:
    - WordPress Cron job scheduled to run periodically (e.g., hourly).
    - Automatically attempts to refresh EVE access tokens for all linked main and alt characters.
    - If tokens are valid/refreshed, it re-fetches and updates skill data for all characters.

Technical Highlights:

  - Utilizes EVE Online SSO (OAuth 2.0) for secure authentication.
  - Interacts with the official EVE Swagger Interface (ESI) for data.
  - Uses WordPress user meta for storing character and skill data.
  - Employs WordPress Transients for caching ESI data like skill names.
  - Uses PHP Sessions for managing SSO state during authentication flow.
  - Leverages WordPress admin-post.php actions for secure form handling.
  - Implements WordPress Cron for background data updates.

How to Use (Basic):

  Install and activate the plugin.
  Site Administrators (or Editors) configure the EVE Application Client ID and Secret Key on the "EVE Skills Settings" page. Ensure the Callback URL matches the one provided on the settings page.
  Place the shortcode [eve_sso_login_button] on any page or post to allow users to start the authentication process.
  Example: [eve_sso_login_button text="Link Your EVE Main Character"]

  Users click the button and are guided through EVE SSO.

  Administrators can review collected skill data under "EVE Skills" -> "View All User Skills."
![image](https://github.com/user-attachments/assets/3072b669-7614-4a43-bf09-9ab552069c8d)
![image](https://github.com/user-attachments/assets/26c7bfc1-035f-4383-9473-9c6cb94e6539)
![image](https://github.com/user-attachments/assets/ebe902ce-2b5d-4e70-91da-3a4ddb0600cc)
![image](https://github.com/user-attachments/assets/c8a155e2-5ed4-4c4a-8422-cc27ae7f96a0)
![image](https://github.com/user-attachments/assets/35c00a05-6b0f-475e-8ca0-6b22aaa07118)


