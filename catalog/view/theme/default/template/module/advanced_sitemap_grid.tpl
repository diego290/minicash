<?php echo '<?xml version="1.0" encoding="UTF-8"?>'; ?>
<xsl:stylesheet version="2.0" 
  xmlns:html="http://www.w3.org/TR/REC-html40"
  xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"
  xmlns:sitemap="http://www.sitemaps.org/schemas/sitemap/0.9"
  xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
	<xsl:output method="html" version="1.0" encoding="UTF-8" indent="yes"/>
	<xsl:template match="/">
		<html xmlns="http://www.w3.org/1999/xhtml">
			<head>
				<title><?php echo $title; ?></title>
				<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
				<style type="text/css">
					body {
						font-family: Helvetica, Arial, sans-serif;
						font-size: 13px;
						color: #545353;
					}
					table {
						border: none;
						border-collapse: collapse;
					}
					#sitemap tr.odd {
						background-color: #eee;
					}
					#totals {
            width:100%;
            margin:20px 0;
          }
					#totals td div {
            font-size:15px;
            width:85%;
            display:inline-block;
            background:#eee;
            border-radius:5px;
            padding:15px;
          }
					#sitemap tbody tr:hover, #totals td div:hover {
						background-color: #D7DEE8;
					}
					#sitemap tbody tr:hover td, #sitemap tbody tr:hover td a {
						color: #000;
					}
					#content {
						margin: 0 auto;
						width: 1100px;
					}
					.expl {
						margin: 10px 3px;
						line-height: 1.3em;
					}
					.expl a {
						color: #447e9e;
						font-weight: bold;
					}
					a {
						color: #333;
						text-decoration: none;
					}
					a:hover {
						text-decoration: underline;
					}
					td {
						font-size:11px;
					}
          .grid div{float:left; padding:15px;}
          .grid div a{display:block;border:3px solid #fff; border-radius:4px; padding:5px 10px;}
          .grid div a:hover{border-color:#678aac;}
				</style>
			</head>
			<body>
				<div id="content">
					<h1 style="text-align:center;margin:50px 0 80px">Product grid</h1>
          
					<div class="grid">
            <xsl:for-each select="sitemap:urlset/sitemap:url">
              <?php if ($type == 'product') { ?>
                <xsl:if test="sitemap:thumb != ''"><div><a href="{sitemap:loc}"><img src="{sitemap:thumb}"/></a></div></xsl:if>
              <?php } ?>
            </xsl:for-each>
          </div>
				</div>
			</body>
		</html>
	</xsl:template>
</xsl:stylesheet>