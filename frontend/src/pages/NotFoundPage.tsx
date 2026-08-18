import { Link } from 'react-router-dom';

function NotFoundPage() {
  return (
    <div className="nova-not-found" role="main">
      <div className="nova-not-found-content">
        <div className="nova-not-found-code" aria-hidden="true">404</div>
        <h1 className="nova-not-found-title">Page Not Found</h1>
        <p className="nova-not-found-message">
          The page you are looking for does not exist or has been moved.
        </p>
        <Link to="/dashboard" id="not-found-home-link" className="nova-btn nova-btn--primary">
          Return to Dashboard
        </Link>
      </div>
    </div>
  );
}

export default NotFoundPage;
