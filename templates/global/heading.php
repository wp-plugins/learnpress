<div class="top_site_main " style="color: #fff">
	<div class="top-site-inner" style="background: #51c4d4;height: 150px;">
		<div class="container">
			<div class="tp-table">
				<div class="page-title-captions ">
					<header class="entry-header">
						<h2 class="page-title">
							<?php
							if ( is_category() ) :
								single_cat_title();
							elseif ( is_single() ) :
								the_title();
							elseif ( is_tag() ) :
								single_tag_title();

							elseif ( is_author() ) :
								printf( __( 'Author: %s', 'thim' ), '<span class="vcard">' . get_the_author() . '</span>' );

							elseif ( is_day() ) :
								printf( __( 'Day: %s', 'thim' ), '<span>' . get_the_date() . '</span>' );

							elseif ( is_month() ) :
								printf( __( 'Month: %s', 'thim' ), '<span>' . get_the_date( _x( 'F Y', 'monthly archives date format', 'thim' ) ) . '</span>' );

							elseif ( is_year() ) :
								printf( __( 'Year: %s', 'thim' ), '<span>' . get_the_date( _x( 'Y', 'yearly archives date format', 'thim' ) ) . '</span>' );
							elseif ( is_search() ) :
								printf( __( 'Search Results for: %s', 'thim' ), '<span>' . get_search_query() . '</span>' );
							elseif ( is_404() ) :
								esc_attr_e( '404', 'thim' );
							else :
								esc_attr_e( 'Course Directory', 'thim' );

							endif;
							?>
						</h2>
					</header>
				</div>
				<div class="breadcrumbs ">
					<?php course_breadcrumb(); ?>
				</div>
			</div>
		</div>
	</div>
</div>