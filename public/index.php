<?php

$pageTitle = 'Home';

require_once __DIR__ . '/../app/includes/header.php';
?>

<section class="hero">
    <div class="hero-content">
        <p class="eyebrow">EVENT TICKET BOOKING</p>

        <h1>Discover Events</h1>

        <p class="hero-description">
            Find events, choose your seats, and book your tickets with ease.
        </p>

        <form class="event-search" action="/events.php" method="get">
            <div class="search-field">
                <label for="search">Search events</label>
                <input
                    type="search"
                    id="search"
                    name="search"
                    placeholder="Search by event name..."
                >
            </div>

            <div class="search-field">
                <label for="category">Category</label>
                <select id="category" name="category">
                    <option value="">All categories</option>
                    <option value="music">Music</option>
                    <option value="sports">Sports</option>
                    <option value="comedy">Comedy</option>
                    <option value="theatre">Theatre</option>
                </select>
            </div>

            <button type="submit" class="btn">
                Search Events
            </button>
        </form>

        <a href="/events.php" class="hero-link">
            Browse all events
        </a>
    </div>
</section>

<section class="featured-events">
    <div class="container">
        <div class="section-heading">
            <p class="eyebrow">WHAT'S HAPPENING</p>

            <h2>Featured Events</h2>

            <p>
                Upcoming events will appear here once the database integration
                is connected.
            </p>
        </div>

        <div class="event-grid">

            <article class="event-card">
                <div class="event-card-image">
                    <span>EVENT IMAGE</span>
                </div>

                <div class="event-card-content">
                    <span class="event-category">Music</span>

                    <h3>Live Music Night</h3>

                    <p class="event-meta">
                        Delhi • 15 October 2026
                    </p>

                    <p class="event-description">
                        Enjoy an evening of live performances and great music.
                    </p>

                    <div class="event-card-footer">
                        <span class="event-price">From ₹499</span>

                        <a href="/event-details.php" class="btn">
                            View Details
                        </a>
                    </div>
                </div>
            </article>


            <article class="event-card">
                <div class="event-card-image">
                    <span>EVENT IMAGE</span>
                </div>

                <div class="event-card-content">
                    <span class="event-category">Sports</span>

                    <h3>Championship Match</h3>

                    <p class="event-meta">
                        Noida • 20 October 2026
                    </p>

                    <p class="event-description">
                        Experience the excitement of a live sporting event.
                    </p>

                    <div class="event-card-footer">
                        <span class="event-price">From ₹799</span>

                        <a href="/event-details.php" class="btn">
                            View Details
                        </a>
                    </div>
                </div>
            </article>


            <article class="event-card">
                <div class="event-card-image">
                    <span>EVENT IMAGE</span>
                </div>

                <div class="event-card-content">
                    <span class="event-category">Comedy</span>

                    <h3>Stand-Up Comedy Night</h3>

                    <p class="event-meta">
                        Gurugram • 25 October 2026
                    </p>

                    <p class="event-description">
                        Spend your evening with live stand-up comedy and entertainment.
                    </p>

                    <div class="event-card-footer">
                        <span class="event-price">From ₹399</span>

                        <a href="/event-details.php" class="btn">
                            View Details
                        </a>
                    </div>
                </div>
            </article>

        </div>
    </div>
</section>

<?php
require_once __DIR__ . '/../app/includes/footer.php';
?>