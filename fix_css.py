import re

with open('/Applications/XAMPP/xamppfiles/htdocs/santycopy/venta.php', 'r') as f:
    content = f.read()

# Replace p colors
content = content.replace('''      #mlb2-37088497.ml-form-embedContainer .ml-form-embedWrapper .ml-form-embedBody .ml-form-embedContent p,
      #mlb2-37088497.ml-form-embedContainer .ml-form-embedWrapper .ml-form-successBody .ml-form-successContent p {
        color: #000000;''', '''      #mlb2-37088497.ml-form-embedContainer .ml-form-embedWrapper .ml-form-embedBody .ml-form-embedContent p,
      #mlb2-37088497.ml-form-embedContainer .ml-form-embedWrapper .ml-form-successBody .ml-form-successContent p {
        color: #c5c5c5;''')

# Replace ul/ol colors
content = content.replace('''      #mlb2-37088497.ml-form-embedContainer .ml-form-embedWrapper .ml-form-embedBody .ml-form-embedContent ul,
      #mlb2-37088497.ml-form-embedContainer .ml-form-embedWrapper .ml-form-embedBody .ml-form-embedContent ol,
      #mlb2-37088497.ml-form-embedContainer .ml-form-embedWrapper .ml-form-successBody .ml-form-successContent ul,
      #mlb2-37088497.ml-form-embedContainer .ml-form-embedWrapper .ml-form-successBody .ml-form-successContent ol {
        color: #000000;''', '''      #mlb2-37088497.ml-form-embedContainer .ml-form-embedWrapper .ml-form-embedBody .ml-form-embedContent ul,
      #mlb2-37088497.ml-form-embedContainer .ml-form-embedWrapper .ml-form-embedBody .ml-form-embedContent ol,
      #mlb2-37088497.ml-form-embedContainer .ml-form-embedWrapper .ml-form-successBody .ml-form-successContent ul,
      #mlb2-37088497.ml-form-embedContainer .ml-form-embedWrapper .ml-form-successBody .ml-form-successContent ol {
        color: #c5c5c5;''')

# Replace a colors
content = content.replace('''      #mlb2-37088497.ml-form-embedContainer .ml-form-embedWrapper .ml-form-embedBody .ml-form-embedContent p a,
      #mlb2-37088497.ml-form-embedContainer .ml-form-embedWrapper .ml-form-successBody .ml-form-successContent p a {
        color: #000000;''', '''      #mlb2-37088497.ml-form-embedContainer .ml-form-embedWrapper .ml-form-embedBody .ml-form-embedContent p a,
      #mlb2-37088497.ml-form-embedContainer .ml-form-embedWrapper .ml-form-successBody .ml-form-successContent p a {
        color: #ffffff;''')

# Replace label colors
content = content.replace('''      #mlb2-37088497.ml-form-embedContainer .ml-form-embedWrapper .ml-block-form .ml-field-group label {
        margin-bottom: 5px;
        color: #000000;''', '''      #mlb2-37088497.ml-form-embedContainer .ml-form-embedWrapper .ml-block-form .ml-field-group label {
        margin-bottom: 5px;
        color: #ffffff;''')

# Replace input styles
content = content.replace('''      #mlb2-37088497.ml-form-embedContainer .ml-form-embedWrapper .ml-form-embedBody .ml-form-fieldRow input {
        background-color: #ffffff !important;
        color: #333333 !important;
        border-color: #cccccc;
        border-radius: 4px !important;
        border-style: solid !important;
        border-width: 0px !important;
        font-family: 'Open Sans', Arial, Helvetica, sans-serif;
        font-size: 10px !important;''', '''      #mlb2-37088497.ml-form-embedContainer .ml-form-embedWrapper .ml-form-embedBody .ml-form-fieldRow input {
        background-color: #18191d !important;
        color: #ffffff !important;
        border-color: #27272a;
        border-radius: 8px !important;
        border-style: solid !important;
        border-width: 1px !important;
        font-family: 'Open Sans', Arial, Helvetica, sans-serif;
        font-size: 16px !important;''')

# Replace placeholder colors
content = content.replace('''::-webkit-input-placeholder { color: #333333; }''', '''::-webkit-input-placeholder { color: #a1a1aa; }''')
content = content.replace('''::-moz-placeholder { color: #333333; }''', '''::-moz-placeholder { color: #a1a1aa; }''')
content = content.replace(''':-ms-input-placeholder { color: #333333; }''', ''':-ms-input-placeholder { color: #a1a1aa; }''')

# Replace textarea styles
content = content.replace('''      #mlb2-37088497.ml-form-embedContainer .ml-form-embedWrapper .ml-form-embedBody .ml-form-fieldRow textarea, #mlb2-37088497.ml-form-embedContainer .ml-form-embedWrapper .ml-form-embedBody .ml-form-horizontalRow textarea {
        background-color: #ffffff !important;
        color: #333333 !important;
        border-color: #cccccc;
        border-radius: 4px !important;
        border-style: solid !important;
        border-width: 0px !important;
        font-family: 'Open Sans', Arial, Helvetica, sans-serif;
        font-size: 10px !important;''', '''      #mlb2-37088497.ml-form-embedContainer .ml-form-embedWrapper .ml-form-embedBody .ml-form-fieldRow textarea, #mlb2-37088497.ml-form-embedContainer .ml-form-embedWrapper .ml-form-embedBody .ml-form-horizontalRow textarea {
        background-color: #18191d !important;
        color: #ffffff !important;
        border-color: #27272a;
        border-radius: 8px !important;
        border-style: solid !important;
        border-width: 1px !important;
        font-family: 'Open Sans', Arial, Helvetica, sans-serif;
        font-size: 16px !important;''')

# Replace form wrapper background if it's white anywhere? No, it's black. 
# But let's check .ml-form-embedWrapper background-color just to be sure.
# It is #000000. 

# Replace button color
content = content.replace('''      #mlb2-37088497.ml-form-embedContainer .ml-form-embedWrapper .ml-form-embedBody .ml-form-embedSubmit button {
        background-color: #ff0000 !important;''', '''      #mlb2-37088497.ml-form-embedContainer .ml-form-embedWrapper .ml-form-embedBody .ml-form-embedSubmit button {
        background-color: #5564F1 !important;''')
content = content.replace('''      #mlb2-37088497.ml-form-embedContainer .ml-form-embedWrapper .ml-form-embedBody .ml-form-embedSubmit button:hover {
        background-color: #730303 !important;''', '''      #mlb2-37088497.ml-form-embedContainer .ml-form-embedWrapper .ml-form-embedBody .ml-form-embedSubmit button:hover {
        background-color: #707df3 !important;''')

with open('/Applications/XAMPP/xamppfiles/htdocs/santycopy/venta.php', 'w') as f:
    f.write(content)

