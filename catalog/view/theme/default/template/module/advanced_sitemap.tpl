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
					#sitemap tr:nth-child(even) {
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
						width: 1000px;
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
					th {
						text-align:left;
						padding-right:30px;
						font-size:11px;
					}
					thead th {
						border-bottom: 1px solid #000;
						cursor: pointer;
					}
				</style>
			</head>
			<body>
				<div id="content">
					<h1><?php echo $title; ?></h1>
          <!--
					<p class="expl">
						Gerado pelo plug-in andcommerce do <a href=""> Advanced Sitemap Generator </a>, este ?? um Sitemap XML, destinado ao consumo por mecanismos de pesquisa.
					</p>
					<p class="expl">
						Voc?? pode encontrar mais informa????es sobre sitemaps XML em <a href="http://sitemaps.org"> sitemaps.org </a>.
					</p>
          -->
					<p class="expl">
          <?php if ($index) { ?>
            This sitemap index contains <xsl:value-of select="count(sitemap:sitemapindex/sitemap:sitemap)"/> sitemaps.
            <table id="totals"><tr>
              <?php foreach($count as $ctype => $tot) { ?>
                <td style="text-align:center;"><div>Total <?php echo $ctype; ?>: <b><?php echo $tot; ?></b></div></td>
              <?php } ?>
            </tr></table>
          <?php } else { ?>
						This sitemap contains <xsl:value-of select="count(sitemap:urlset/sitemap:url)"/> URLs.
            <p>Return to <a href="<?php echo $index_link; ?>">sitemap index</a></p>
          <?php } ?>
					</p>			
					<table id="sitemap" cellpadding="3">
						<thead>
							<tr>
                <?php if ($type == 'product' && !empty($cfg['display_img'])) { ?>
								<th width="5%">Image</th>
                <?php } ?>
								<th width="75%">URL</th>
                <?php if (!$index) { ?>
								<th width="5%">Priority</th>
								<th width="5%">Change Freq.</th>
                 <?php } ?>
								<th width="10%">Last Change</th>
							</tr>
						</thead>
						<tbody>
							<xsl:variable name="lower" select="'abcdefghijklmnopqrstuvwxyz'"/>
							<xsl:variable name="upper" select="'ABCDEFGHIJKLMNOPQRSTUVWXYZ'"/>
              <?php if ($index) { ?>
              <xsl:for-each select="sitemap:sitemapindex/sitemap:sitemap">
              <?php } else { ?>
							<xsl:for-each select="sitemap:urlset/sitemap:url">
              <?php } ?>
								<tr>
                  <?php if ($type == 'product' && !empty($cfg['display_img'])) { ?>
									<td><xsl:if test="@thumb!= ''"><img width="50" height="50" src="{@thumb}"/></xsl:if></td>
                   <?php } ?>
									<td>
										<xsl:variable name="itemURL">
											<xsl:value-of select="sitemap:loc"/>
										</xsl:variable>
										<a href="{$itemURL}">
											<xsl:value-of select="sitemap:loc"/>
										</a>
									</td>
                  <?php if (!$index) { ?>
									<td>
										<xsl:value-of select="concat(sitemap:priority*100,'%')"/>
									</td>
                  <?php /* ?>
									<td><xsl:value-of select="count(image:image)"/></td>
                  <?php */ ?>
									<td>
										<xsl:value-of select="concat(translate(substring(sitemap:changefreq, 1, 1),concat($lower, $upper),concat($upper, $lower)),substring(sitemap:changefreq, 2))"/>
									</td>
                  <?php } ?>
									<td>
										<xsl:value-of select="concat(substring(sitemap:lastmod,0,11),concat(' ', substring(sitemap:lastmod,12,5)))"/>
									</td>
								</tr>
							</xsl:for-each>
						</tbody>
					</table>
				</div>
			</body>
		</html>
	</xsl:template>
</xsl:stylesheet>