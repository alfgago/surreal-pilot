# Page snapshot

```yaml
- main:
  - text: Laravel
  - heading "Sign up" [level=1]
  - paragraph:
    - text: or
    - link "sign in to your account":
      - /url: http://surreal-pilot.local/company/login
  - text: Name
  - superscript: "*"
  - textbox "Name*"
  - text: Email address
  - superscript: "*"
  - textbox "Email address*"
  - text: Password
  - superscript: "*"
  - textbox "Password*"
  - button "Show password"
  - text: Confirm password
  - superscript: "*"
  - textbox "Confirm password*"
  - button "Show password"
  - checkbox "I agree to the Terms of Service and Privacy Policy"
  - text: I agree to the
  - link "Terms of Service":
    - /url: http://surreal-pilot.local/company/terms-of-service
  - text: and
  - link "Privacy Policy":
    - /url: http://surreal-pilot.local/company/privacy-policy
  - button "Sign up"
- status
```