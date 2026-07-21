import re

with open('/Applications/XAMPP/xamppfiles/htdocs/santycopy/venta.php', 'r') as f:
    content = f.read()

# I will find the start of the injected form
form_start = content.find('<style type="text/css">@import url("https://assets.mlcdn.com/fonts.css?version=1770645");</style>')

if form_start != -1:
    content = content[:form_start] + '  </body>\n\n</html>'

with open('/Applications/XAMPP/xamppfiles/htdocs/santycopy/venta.php', 'w') as f:
    f.write(content)

