import datetime
import html
import http.cookiejar
import re
import subprocess
import time
import urllib.error
import urllib.parse
import urllib.request

BASE = "http://127.0.0.1:8000/"
PASSWORD = "Filmatic-test-123!"


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


class Browser:
    def __init__(self):
        self.opener = urllib.request.build_opener(
            urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()),
            NoRedirect(),
        )

    def request(self, path, data=None, expected=200):
        body = urllib.parse.urlencode(data, doseq=True).encode() if data is not None else None
        req = urllib.request.Request(BASE + path, data=body)
        try:
            response = self.opener.open(req, timeout=10)
        except urllib.error.HTTPError as exc:
            response = exc
        with response:
            content = response.read().decode("utf-8", "replace")
            assert response.code == expected, f"{path}: HTTP {response.code}, attendu {expected}"
            assert not re.search(r"Fatal error|Uncaught (?:Error|PDOException)|Warning:|Parse error", content), f"Erreur PHP sur {path}"
            return content, response.headers

    def get(self, path, expected=200):
        return self.request(path, expected=expected)[0]

    def post(self, path, fields, expected=200):
        form = self.get(path)
        token = re.search(r'name="csrf_token" value="([a-f0-9]{64})"', form)
        assert token, f"Jeton CSRF absent sur {path}"
        return self.request(path, {**fields, "csrf_token": html.unescape(token.group(1))}, expected)[0]


def query(sql):
    return subprocess.check_output(
        ["php", "-r", "$c=require 'config.local.php'; $p=new PDO('mysql:host='.$c['host'].';port='.$c['port'].';dbname='.$c['database'].';charset=utf8mb4',$c['username'],$c['password']); $s=$p->query($argv[1]); if($s->columnCount()) echo $s->fetchColumn();", sql],
        text=True,
    ).strip()


def register(browser, username):
    browser.post("register.php", {
        "username": username,
        "email": username + "@example.invalid",
        "password": PASSWORD,
        "confirm_password": PASSWORD,
    })
    assert query("SELECT COUNT(*) FROM users WHERE username='" + username + "'") == "1"


def login(browser, username):
    browser.post("login.php", {"username": username, "password": PASSWORD}, expected=302)


def main():
    for _ in range(30):
        try:
            urllib.request.urlopen(BASE, timeout=2).close()
            break
        except (OSError, urllib.error.URLError):
            time.sleep(1)
    else:
        raise RuntimeError("Serveur de test indisponible")

    assert query("SELECT COUNT(*) FROM users") == "0", "Utiliser uniquement une base de test neuve"
    visitor = Browser()
    for path in ("", "events.php", "proposals.php", "Mentions-legales.php", "login.php", "register.php"):
        visitor.get(path)
    visitor.get("profile.php", expected=302)
    visitor.get("admin/dashboard.php", expected=302)
    visitor.request("register.php", {"username": "invalid_csrf"}, expected=403)
    register(visitor, "demo_organisateur")
    query("UPDATE users SET role='organizer' WHERE username='demo_organisateur'")
    login(visitor, "demo_organisateur")
    visitor.get("profile.php")
    visitor.get("admin/dashboard.php", expected=302)
    date = (datetime.datetime.now() + datetime.timedelta(days=10)).strftime("%Y-%m-%dT%H:%M")
    visitor.post("create-event.php", {
        "title": "Séance de démonstration",
        "event_date": date,
        "movie_titles[]": ["Film A", "Film B", "Film C"],
        "movie_genres[]": ["", "", ""],
        "movie_langs[]": ["VF", "VF", "VF"],
        "loc_names[]": ["Salle A", "Salle B", "Salle C"],
        "loc_mins[]": ["0", "0", "0"],
        "loc_maxs[]": ["50", "50", "50"],
    })
    assert query("SELECT COUNT(*) FROM events WHERE organizer_id IS NOT NULL AND organizer_id=created_by") == "1"
    event_id = query("SELECT id FROM events LIMIT 1")
    visitor.get("edit-event.php?id=" + event_id)
    visitor.post("edit-event.php?id=" + event_id, {"title": "Séance modifiée", "event_date": date, "status": "voting"})
    assert query("SELECT title FROM events WHERE id=" + event_id) == "Séance modifiée"

    participant = Browser()
    register(participant, "demo_participant")
    login(participant, "demo_participant")
    participant.get("create-event.php", expected=302)
    participant.post("proposals.php", {"type": "film", "title_name": "Film de test", "description": "Suggestion fictive", "submit_proposal": "1"})
    assert query("SELECT COUNT(*) FROM proposals") == "1"
    path = "events.php?id=" + event_id
    films = query("SELECT GROUP_CONCAT(id ORDER BY id) FROM event_movies WHERE event_id=" + event_id)
    locations = query("SELECT GROUP_CONCAT(id ORDER BY id) FROM event_locations WHERE event_id=" + event_id)
    bad = ",".join([films.split(",")[0]] * 3)
    participant.post(path, {"submit_vote": "1", "film_order": bad, "location_order": locations})
    assert query("SELECT COUNT(*) FROM votes") == "0"
    vote = {"submit_vote": "1", "film_order": films, "location_order": locations}
    token = re.search(r'name="csrf_token" value="([a-f0-9]{64})"', participant.get(path))[1]
    participant.request(path, {**vote, "csrf_token": token})
    assert query("SELECT COUNT(*) FROM votes") == "6"
    participant.request(path, {**vote, "csrf_token": token})
    assert query("SELECT COUNT(*) FROM votes") == "6"

    query("UPDATE users SET role='admin' WHERE username='demo_organisateur'")
    for path in ("admin/dashboard.php", "admin/users.php", "admin/events.php", "admin/proposals.php", "recruitements/dashboard.php"):
        visitor.get(path)
    token = re.search(r'name="csrf_token" value="([a-f0-9]{64})"', visitor.get(""))[1]
    visitor.request("logout.php", {"csrf_token": token}, expected=302)
    visitor.get("profile.php", expected=302)
    print("Parcours principaux : OK")


if __name__ == "__main__":
    main()
