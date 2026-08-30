<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    body { font-family: sans-serif; font-size: 12px; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; }
    th { background: #f0f0f0; }
  </style>
</head>
<body>
  <h3>Drivers</h3>
  <table>
    <thead>
      <tr>
        <th>ID</th>
        <th>Driver Name</th>
        <th>Affiliation</th>
        <th>Driver Availability</th>
      </tr>
    </thead>
    <tbody>
      @foreach ($drivers as $driver)
        <tr>
          <td>{{ $driver->user->code }}</td>
          <td>{{ $driver->user->name }}</td>
          <td>{{ $driver->user->affiliation === 'gcm' ? 'GCM' : $driver->user->affiliation }}</td>
          <td>{{ $driver->user->status }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>
</body>
</html>
