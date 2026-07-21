import re

with open('/Applications/XAMPP/xamppfiles/htdocs/santycopy/venta.php', 'r') as f:
    content = f.read()

# Replace padding-top:4.0rem;padding-bottom:2.0rem; with padding-top:0rem;padding-bottom:0rem;
# Wait, I only want to replace it for the section that contains the script, or just replace all 2 of them since they might both be empty space.
content = content.replace('padding-top:4.0rem;padding-bottom:2.0rem;', 'padding-top:0rem;padding-bottom:0rem;')

# Also the padding-bottom:2rem; right before the text "Dale. Completá este formulario y hablemos."
# It looks like `<div class="transition-all duration-150" style="padding-bottom:2rem;--element-padding-bottom-mobile:;" root-parent-uuid="Gx-Sx_rDAA8Al8c6U5Lyq">`
# Let's reduce it to padding-bottom:0rem;
content = content.replace('padding-bottom:2rem;--element-padding-bottom-mobile:;" root-parent-uuid="Gx-Sx_rDAA8Al8c6U5Lyq"', 'padding-bottom:0rem;--element-padding-bottom-mobile:;" root-parent-uuid="Gx-Sx_rDAA8Al8c6U5Lyq"')

with open('/Applications/XAMPP/xamppfiles/htdocs/santycopy/venta.php', 'w') as f:
    f.write(content)

